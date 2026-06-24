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

    // ── Multi-country filter tests (countryCodes[]) ────────────────────────────

    public function testKpiSummaryFiltersBySingleCountryCode(): void
    {
        // Approach: FR alone < FR+ES combined — proves the filter reduces the scope
        $client = static::createClient();
        $headers = [
            'Authorization' => 'Bearer ' . self::$token,
            'Accept' => 'application/json',
        ];

        $client->request('GET', '/kpi/summary?' . http_build_query([
            'resourceCategory' => 'ELEC',
            'month' => '2024-01',
            'countryCodes' => ['FR'],
        ]), ['headers' => $headers]);
        $this->assertResponseIsSuccessful();
        $frData = json_decode(static::getClient()->getResponse()->getContent(), true);
        $frTotal = (float) ($frData[0]['totalConsumptionMtd'] ?? 0);

        $client->request('GET', '/kpi/summary?' . http_build_query([
            'resourceCategory' => 'ELEC',
            'month' => '2024-01',
            'countryCodes' => ['FR', 'ES'],
        ]), ['headers' => $headers]);
        $this->assertResponseIsSuccessful();
        $bothData = json_decode(static::getClient()->getResponse()->getContent(), true);
        $bothTotal = (float) ($bothData[0]['totalConsumptionMtd'] ?? 0);

        $this->assertGreaterThan(
            $frTotal,
            $bothTotal,
            'Adding ES to the country filter must increase total consumption',
        );
    }

    public function testKpiSummaryFiltersMultipleCountryCodes(): void
    {
        // FR + ES → total must equal sum of both fixtures (100k + 80k = 180k)
        static::createClient()->request(
            'GET',
            '/kpi/summary?' . http_build_query([
                'resourceCategory' => 'ELEC',
                'month' => '2024-01',
                'countryCodes' => ['FR', 'ES'],
            ]),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('totalConsumptionMtd', $data[0]);
        // FR (100k) + ES (80k) = 180k minimum (other test runs may have added sites)
        $this->assertGreaterThanOrEqual(180_000.0, (float) $data[0]['totalConsumptionMtd']);
    }

    public function testKpiSummaryWithNoCountryCodesReturnsAll(): void
    {
        // No filter → must include both FR and ES (total >= 180k)
        static::createClient()->request('GET', '/kpi/summary?resourceCategory=ELEC&month=2024-01', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::$token,
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
        $this->assertGreaterThanOrEqual(180_000.0, (float) $data[0]['totalConsumptionMtd']);
    }

    public function testSiteRankingFiltersBySingleCountryCode(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/site-ranking?' . http_build_query([
                'resourceCategory' => 'ELEC',
                'month' => '2024-01',
                'countryCodes' => ['FR'],
                'limit' => 50,
            ]),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $countryCodes = array_column($data, 'countryCode');

        // All returned sites must be French
        foreach ($countryCodes as $code) {
            $this->assertSame('FR', $code, "Expected only FR sites, got: {$code}");
        }
    }

    // ── /kpi/country-intensity ─────────────────────────────────────────────────

    public function testCountryIntensityRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/kpi/country-intensity');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCountryIntensityReturnsExpectedStructure(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/country-intensity?resourceCategory=ELEC&month=2024-01',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('countryCode', $first);
        // intensity may be null if no area data, but key must exist when not null
        if (isset($first['intensity'])) {
            $this->assertIsFloat($first['intensity']);
            $this->assertGreaterThan(0, $first['intensity']);
        }
    }

    public function testCountryIntensityReturnsBothCountriesWithoutFilter(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/country-intensity?resourceCategory=ELEC&month=2024-01',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $codes = array_column($data, 'countryCode');

        $this->assertContains('FR', $codes);
        $this->assertContains('ES', $codes);
    }

    public function testCountryIntensityFiltersByCountryCode(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/country-intensity?' . http_build_query([
                'resourceCategory' => 'ELEC',
                'month' => '2024-01',
                'countryCodes' => ['FR'],
            ]),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $codes = array_column($data, 'countryCode');

        $this->assertContains('FR', $codes);
        $this->assertNotContains('ES', $codes);
    }

    // ── /kpi/refrigerant-by-country ────────────────────────────────────────────

    public function testRefrigerantByCountryRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/kpi/refrigerant-by-country');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefrigerantByCountryReturnsExpectedStructure(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/refrigerant-by-country?month=2024-01',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('countryCode', $first);
        $this->assertArrayHasKey('totalKg', $first);
        $this->assertArrayHasKey('quarterStart', $first);
        $this->assertArrayHasKey('quarterEnd', $first);
        $this->assertGreaterThan(0, $first['totalKg']);
    }

    public function testRefrigerantByCountryReturnsCorrectQtdRange(): void
    {
        // January → Q1 → quarterStart = 2024-01
        static::createClient()->request(
            'GET',
            '/kpi/refrigerant-by-country?month=2024-01',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
        $this->assertSame('2024-01', $data[0]['quarterStart']);
        $this->assertSame('2024-01', $data[0]['quarterEnd']);
    }

    public function testRefrigerantByCountryFiltersBySingleCountryCode(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/refrigerant-by-country?' . http_build_query([
                'month' => '2024-01',
                'countryCodes' => ['FR'],
            ]),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $codes = array_column($data, 'countryCode');

        $this->assertContains('FR', $codes);
        // ES has no refrigerant fixture in KpiTest → even without filter it wouldn't appear,
        // but the filter must not break the query
        $this->assertNotContains('ES', $codes);
    }

    public function testMonthlyEvolutionFiltersMultipleCountryCodes(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/monthly-evolution?' . http_build_query([
                'resourceCategory' => 'ELEC',
                'year' => 2024,
                'countryCodes' => ['FR', 'ES'],
            ]),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertCount(12, $data, 'Monthly evolution must always return 12 months regardless of filter');

        // Jan must have current data (both FR and ES have 2024-01 fixtures)
        $jan = array_values(array_filter($data, fn (array $m) => $m['month'] === '2024-01'));
        $this->assertNotEmpty($jan);
        $this->assertArrayHasKey('current', $jan[0]);
        // FR (100k) + ES (80k) = 180k minimum
        $this->assertGreaterThanOrEqual(180_000.0, (float) $jan[0]['current']);
    }

    // ── /kpi/country-intensity-monthly ────────────────────────────────────────

    public function testCountryIntensityMonthlyRequiresAuth(): void
    {
        static::createClient()->request('GET', '/kpi/country-intensity-monthly?resourceCategory=ELEC&year=2024', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCountryIntensityMonthlyReturnsArrayWithExpectedShape(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/country-intensity-monthly?' . http_build_query([
                'resourceCategory' => 'ELEC',
                'year' => 2024,
                'countryCodes' => ['FR'],
            ]),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        // Must have at least one entry for FR 2024
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('month', $first);
        $this->assertArrayHasKey('countryCode', $first);
    }

    // ── /kpi/refrigerant-by-quarter ───────────────────────────────────────────

    public function testRefrigerantByQuarterRequiresAuth(): void
    {
        static::createClient()->request('GET', '/kpi/refrigerant-by-quarter?month=2024-03', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefrigerantByQuarterReturnsArrayWithExpectedShape(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/refrigerant-by-quarter?month=2024-03',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        if (!empty($data)) {
            $first = $data[0];
            $this->assertArrayHasKey('quarter', $first);
            $this->assertArrayHasKey('countryCode', $first);
            $this->assertArrayHasKey('totalKg', $first);
        }
    }

    // ── /kpi/refrigerant-breakdown ────────────────────────────────────────────

    public function testRefrigerantBreakdownRequiresAuth(): void
    {
        static::createClient()->request('GET', '/kpi/refrigerant-breakdown?month=2024-03', [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefrigerantBreakdownReturnsPercentages(): void
    {
        static::createClient()->request(
            'GET',
            '/kpi/refrigerant-breakdown?month=2024-03',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$token,
                    'Accept' => 'application/json',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode(static::getClient()->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        if (!empty($data)) {
            $first = $data[0];
            $this->assertArrayHasKey('fluidType', $first);
            $this->assertArrayHasKey('totalKg', $first);
            $this->assertArrayHasKey('percentage', $first);

            // Percentages must sum to ~100
            $totalPct = array_sum(array_column($data, 'percentage'));
            $this->assertEqualsWithDelta(100.0, $totalPct, 0.5);
        }
    }
}
