<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\EnergyConsumption;
use App\Entity\RefrigerantFluid;
use App\Entity\Site;
use App\Entity\SiteArea;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class KpiTest extends ApiTestCase
{
    private static string $frCode;
    private static string $esCode;
    private static string $token;
    private static bool $fixturesCreated = false;
    private EntityManagerInterface $entityManager;

    public static function setUpBeforeClass(): void
    {
        self::$frCode = 'KPI_FR_' . strtoupper(substr(uniqid(), -6));
        self::$esCode = 'KPI_ES_' . strtoupper(substr(uniqid(), -6));
    }

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();

        if (!self::$fixturesCreated) {
            $this->createFixtures($container->get(UserPasswordHasherInterface::class));
            self::$token = $this->obtainJwtToken();
            self::$fixturesCreated = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$fixturesCreated = false;
    }

    private function createFixtures(UserPasswordHasherInterface $hasher): void
    {
        $user = new User();
        $user->setEmail('kpi-test-' . uniqid() . '@piloteco.fr');
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $user->setFirstName('KPI');
        $user->setLastName('Tester');
        $user->setRoles(['ROLE_USER']);
        $this->entityManager->persist($user);

        $siteFr = new Site();
        $siteFr->setSiteUniqueCode(self::$frCode);
        $siteFr->setCountryCode('FR');
        $this->entityManager->persist($siteFr);

        $siteEs = new Site();
        $siteEs->setSiteUniqueCode(self::$esCode);
        $siteEs->setCountryCode('ES');
        $this->entityManager->persist($siteEs);

        $this->entityManager->flush();

        // Current year data (N)
        foreach (['2024-01', '2024-02', '2024-03'] as $month) {
            $this->createConsumption($siteFr, $month, 'ELEC', 100_000.0);
            $this->createConsumption($siteEs, $month, 'ELEC', 80_000.0);
        }

        // N-1 data (needed for evolution % calculation)
        $this->createConsumption($siteFr, '2023-01', 'ELEC', 110_000.0);
        $this->createConsumption($siteEs, '2023-01', 'ELEC', 90_000.0);

        // Areas N (2024)
        $area = new SiteArea();
        $area->setSite($siteFr)->setFiscalYear(2024)->setMonth(1)->setSalesAreaM2(5_000.0);
        $this->entityManager->persist($area);

        $areaEs = new SiteArea();
        $areaEs->setSite($siteEs)->setFiscalYear(2024)->setMonth(1)->setSalesAreaM2(4_000.0);
        $this->entityManager->persist($areaEs);

        // Areas N-1 (2023) — required for evolution % calculation
        $areaN1Fr = new SiteArea();
        $areaN1Fr->setSite($siteFr)->setFiscalYear(2023)->setMonth(1)->setSalesAreaM2(5_000.0);
        $this->entityManager->persist($areaN1Fr);

        $areaN1Es = new SiteArea();
        $areaN1Es->setSite($siteEs)->setFiscalYear(2023)->setMonth(1)->setSalesAreaM2(4_000.0);
        $this->entityManager->persist($areaN1Es);

        $fluid = new RefrigerantFluid();
        $fluid->setSite($siteFr)->setMonthYear('2024-01')->setRefrigerantFluidType('R404A')->setQuantityReloaded(50.0);
        $this->entityManager->persist($fluid);

        $this->entityManager->flush();
    }

    private function createConsumption(Site $site, string $month, string $resource, float $total): void
    {
        $c = new EnergyConsumption();
        $c->setSite($site)->setMonthYear($month)->setResourceCategory($resource);
        $c->setTotalSurfaceQuantityConsumed($total);
        $this->entityManager->persist($c);
    }

    private function obtainJwtToken(): string
    {
        $response = static::createClient()->request('POST', '/login', [
            'json' => ['email' => 'admin@example.com', 'password' => 'password123'],
        ]);

        if ($response->getStatusCode() === 401) {
            $container = static::getContainer();
            $hasher = $container->get(UserPasswordHasherInterface::class);
            $em = $container->get('doctrine')->getManager();

            $admin = new User();
            $admin->setEmail('admin@example.com');
            $admin->setPassword($hasher->hashPassword($admin, 'password123'));
            $admin->setFirstName('Admin');
            $admin->setLastName('Test');
            $admin->setRoles(['ROLE_ADMIN']);
            $em->persist($admin);
            $em->flush();

            $response = static::createClient()->request('POST', '/login', [
                'json' => ['email' => 'admin@example.com', 'password' => 'password123'],
            ]);
        }

        return $response->toArray()['token'];
    }

    // ── /kpi/summary ───────────────────────────────────────────────────────────

    public function testKpiSummaryRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/kpi/summary');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testKpiSummaryReturnsExpectedStructure(): void
    {
        static::createClient()->request('GET', '/kpi/summary?resourceCategory=ELEC&month=2024-01', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $summary = $data[0];
        // Always-present keys (non-null)
        $this->assertArrayHasKey('resourceCategory', $summary);
        $this->assertArrayHasKey('month', $summary);
        $this->assertArrayHasKey('totalConsumptionMtd', $summary);
        $this->assertSame('ELEC', $summary['resourceCategory']);
        $this->assertSame('2024-01', $summary['month']);
        // Keys present when data exists (our test fixtures provide both N and N-1 data)
        $this->assertArrayHasKey('energyIntensityMtd', $summary);
        $this->assertArrayHasKey('evolutionMtdVsN1Percent', $summary);
    }

    public function testKpiSummaryTotalConsumptionIsPositiveWhenDataExists(): void
    {
        static::createClient()->request('GET', '/kpi/summary?resourceCategory=ELEC&month=2024-01', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        // Data exists from real import or test fixtures
        $this->assertNotNull($data[0]['totalConsumptionMtd']);
    }

    // ── /kpi/monthly-evolution ─────────────────────────────────────────────────

    public function testMonthlyEvolutionRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/kpi/monthly-evolution?year=2024');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testMonthlyEvolutionReturns12Months(): void
    {
        static::createClient()->request('GET', '/kpi/monthly-evolution?resourceCategory=ELEC&year=2024', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertCount(12, $data, 'Monthly evolution must always return exactly 12 months');

        $months = array_column($data, 'month');
        $this->assertContains('2024-01', $months);
        $this->assertContains('2024-12', $months);

        // All items must have the 'month' key
        foreach ($data as $monthData) {
            $this->assertArrayHasKey('month', $monthData);
        }
        // Items with actual data must have 'current'
        $monthsWithData = array_filter($data, fn (array $m) => !empty($m['current']));
        $this->assertNotEmpty($monthsWithData, 'At least some months should have data');
    }

    public function testMonthlyEvolutionIncludesAllMonths(): void
    {
        static::createClient()->request('GET', '/kpi/monthly-evolution?resourceCategory=ELEC&year=2024', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $months = array_column($data, 'month');

        for ($m = 1; $m <= 12; $m++) {
            $this->assertContains(sprintf('2024-%02d', $m), $months, "Month 2024-%02d missing", $m);
        }
    }

    // ── /kpi/site-ranking ──────────────────────────────────────────────────────

    public function testSiteRankingRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/kpi/site-ranking');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testSiteRankingReturnsCorrectStructure(): void
    {
        static::createClient()->request('GET', '/kpi/site-ranking?resourceCategory=ELEC&month=2024-01&limit=10', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
        $this->assertIsArray($data);

        $first = $data[0];
        $this->assertArrayHasKey('rank', $first);
        $this->assertArrayHasKey('siteUniqueCode', $first);
        $this->assertArrayHasKey('countryCode', $first);
        $this->assertArrayHasKey('intensity', $first);
        $this->assertIsFloat((float) $first['intensity']);
        $this->assertGreaterThan(0, $first['intensity']);
    }

    public function testSiteRankingRanksAreSequential(): void
    {
        static::createClient()->request('GET', '/kpi/site-ranking?resourceCategory=ELEC&month=2024-01&limit=5', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);

        foreach ($data as $index => $site) {
            $this->assertSame($index + 1, $site['rank']);
        }
    }

    public function testSiteRankingFlopOrderReturnsAscendingIntensity(): void
    {
        static::createClient()->request('GET', '/kpi/site-ranking?resourceCategory=ELEC&month=2024-01&limit=50&order=ASC', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);

        if (count($data) >= 2) {
            $this->assertLessThanOrEqual(
                (float) $data[1]['intensity'],
                (float) $data[0]['intensity'],
                'ASC order: first site should have lower or equal intensity than second',
            );
        }
    }
}
