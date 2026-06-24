<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\RefrigerantFluid;
use App\Entity\Site;
use App\Repository\RefrigerantFluidRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RefrigerantFluidRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RefrigerantFluidRepository $repository;

    /** Unique prefix per test run to avoid constraint conflicts */
    private string $prefix;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(RefrigerantFluid::class);
        $this->prefix = strtoupper(substr(uniqid('RF', true), 0, 8));

        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        $siteFr = $this->createSite("{$this->prefix}_FR_001", 'FR');
        $siteEs = $this->createSite("{$this->prefix}_ES_001", 'ES');

        // FR: Q1 2024 reloads
        $this->createFluid($siteFr, '2024-01', 'R404A', 100.0);
        $this->createFluid($siteFr, '2024-02', 'R404A', 50.0);
        $this->createFluid($siteFr, '2024-03', 'R410A', 75.0);

        // ES: only January
        $this->createFluid($siteEs, '2024-01', 'R404A', 60.0);

        // Q2 data (should be excluded in Q1 queries)
        $this->createFluid($siteFr, '2024-04', 'R404A', 200.0);

        $this->entityManager->flush();
    }

    private function createSite(string $code, string $country): Site
    {
        $site = new Site();
        $site->setSiteUniqueCode($code);
        $site->setCountryCode($country);
        $this->entityManager->persist($site);
        return $site;
    }

    private function createFluid(Site $site, string $monthYear, string $type, float $qty): void
    {
        $fluid = new RefrigerantFluid();
        $fluid->setSite($site);
        $fluid->setMonthYear($monthYear);
        $fluid->setRefrigerantFluidType($type);
        $fluid->setQuantityReloaded($qty);
        $this->entityManager->persist($fluid);
    }

    protected function tearDown(): void
    {
        $sites = $this->entityManager->getRepository(Site::class)
            ->findBy(['countryCode' => ['FR', 'ES']]);

        foreach ($sites as $site) {
            if (str_starts_with($site->getSiteUniqueCode(), $this->prefix)) {
                $this->entityManager->remove($site);
            }
        }
        $this->entityManager->flush();

        parent::tearDown();
        $this->entityManager->close();
    }

    // ── sumByMonthRange ────────────────────────────────────────────────────────

    public function testSumByMonthRangeReturnsTotalPerMonth(): void
    {
        // Other tests (KpiTest) also create refrigerant data for 2024-01 → use minimum bounds
        $results = $this->repository->sumByMonthRange('2024-01', '2024-03');
        $totals = array_column($results, 'total_kg', 'month_year');

        // Jan: our FR(100) + ES(60) = at least 160 kg
        $this->assertArrayHasKey('2024-01', $totals);
        $this->assertGreaterThanOrEqual(160.0, (float) $totals['2024-01']);
        // Feb: our FR(50) = at least 50 kg
        $this->assertArrayHasKey('2024-02', $totals);
        $this->assertGreaterThanOrEqual(50.0, (float) $totals['2024-02']);
    }

    public function testSumByMonthRangeExcludesOutOfRangeMonths(): void
    {
        $results = $this->repository->sumByMonthRange('2024-01', '2024-03');
        $months = array_column($results, 'month_year');

        // April (Q2) must not appear
        $this->assertNotContains('2024-04', $months);
    }

    // ── sumByCountryAndMonthRange ──────────────────────────────────────────────

    public function testSumByCountryAggregatesPerCountry(): void
    {
        // Our Q1 2024 fixtures: FR = 225 kg, ES = 60 kg (other test data may add more)
        $results = $this->repository->sumByCountryAndMonthRange('2024-01', '2024-03');
        $totals = array_column($results, 'total_kg', 'country_code');

        $this->assertArrayHasKey('FR', $totals);
        $this->assertGreaterThanOrEqual(225.0, (float) $totals['FR']);
    }

    public function testSumByCountryFiltersCountryCodes(): void
    {
        $results = $this->repository->sumByCountryAndMonthRange('2024-01', '2024-03', ['FR']);
        $totals = array_column($results, 'total_kg', 'country_code');

        $this->assertArrayHasKey('FR', $totals);
        $this->assertArrayNotHasKey('ES', $totals);
    }

    public function testSumByCountryWithNullCountryCodesReturnsAll(): void
    {
        $results = $this->repository->sumByCountryAndMonthRange('2024-01', '2024-03', null);
        $totals = array_column($results, 'total_kg', 'country_code');

        $this->assertArrayHasKey('FR', $totals);
        $this->assertArrayHasKey('ES', $totals);
    }

    public function testSumByCountryExcludesOutOfRangeMonths(): void
    {
        // Q1 range must yield less than Q1+Q2 range (April adds 200 kg for FR)
        $resultsQ1 = $this->repository->sumByCountryAndMonthRange('2024-01', '2024-03');
        $totalQ1 = (float) (array_column($resultsQ1, 'total_kg', 'country_code')['FR'] ?? 0);

        $resultsQ1Q2 = $this->repository->sumByCountryAndMonthRange('2024-01', '2024-04');
        $totalQ1Q2 = (float) (array_column($resultsQ1Q2, 'total_kg', 'country_code')['FR'] ?? 0);

        // April adds at least 200 kg → Q1+Q2 must be strictly greater
        $this->assertGreaterThan($totalQ1, $totalQ1Q2, 'Extending range to April must increase FR total');
    }

    public function testSumByCountryWithUnknownCountryReturnsEmpty(): void
    {
        $results = $this->repository->sumByCountryAndMonthRange('2024-01', '2024-03', ['ZZ']);
        $this->assertEmpty($results);
    }

    // ── monthlyByCountry ───────────────────────────────────────────────────────

    public function testMonthlyByCountryReturnsOneEntryPerCountryPerMonth(): void
    {
        $results = $this->repository->monthlyByCountry('2024-01', '2024-03', [$this->prefix . '_FR_001', $this->prefix . '_ES_001']);
        // Country-filtered by site code doesn't work (it's by country code) — use country codes instead
        $results = $this->repository->monthlyByCountry('2024-01', '2024-03', null);

        $keyed = [];
        foreach ($results as $row) {
            $keyed[$row['country_code'] . '_' . $row['month_year']] = (float) $row['total_kg'];
        }

        // FR Jan: 100 (R404A), FR Feb: 50 (R404A), FR Mar: 75 (R410A), ES Jan: 60
        $this->assertGreaterThanOrEqual(100.0, $keyed['FR_2024-01'] ?? 0.0);
        $this->assertGreaterThanOrEqual(50.0, $keyed['FR_2024-02'] ?? 0.0);
        $this->assertGreaterThanOrEqual(60.0, $keyed['ES_2024-01'] ?? 0.0);
    }

    public function testMonthlyByCountryExcludesOutOfRange(): void
    {
        $results = $this->repository->monthlyByCountry('2024-01', '2024-03', null);
        $months = array_unique(array_column($results, 'month_year'));

        $this->assertNotContains('2024-04', $months);
    }

    // ── sumByFluidType ─────────────────────────────────────────────────────────

    public function testSumByFluidTypeAggregatesPerType(): void
    {
        $results = $this->repository->sumByFluidType('2024-01', '2024-03');
        $totals = array_column($results, 'total_kg', 'fluid_type');

        // R404A: FR(100+50) + ES(60) = at least 210 kg
        $this->assertArrayHasKey('R404A', $totals);
        $this->assertGreaterThanOrEqual(210.0, (float) $totals['R404A']);

        // R410A: FR(75) = at least 75 kg
        $this->assertArrayHasKey('R410A', $totals);
        $this->assertGreaterThanOrEqual(75.0, (float) $totals['R410A']);
    }

    public function testSumByFluidTypeOrderedByTotalDesc(): void
    {
        $results = $this->repository->sumByFluidType('2024-01', '2024-03');

        // Verify descending order (largest total first)
        $totals = array_column($results, 'total_kg');
        for ($i = 1; $i < count($totals); $i++) {
            $this->assertGreaterThanOrEqual((float) $totals[$i], (float) $totals[$i - 1]);
        }
    }

    public function testSumByFluidTypeExcludesOutOfRangeMonths(): void
    {
        $resultsQ1 = $this->repository->sumByFluidType('2024-01', '2024-03');
        $totalQ1 = (float) (array_column($resultsQ1, 'total_kg', 'fluid_type')['R404A'] ?? 0);

        $resultsQ1Q2 = $this->repository->sumByFluidType('2024-01', '2024-04');
        $totalQ1Q2 = (float) (array_column($resultsQ1Q2, 'total_kg', 'fluid_type')['R404A'] ?? 0);

        // April adds 200 kg of R404A → Q1+Q2 must be strictly greater
        $this->assertGreaterThan($totalQ1, $totalQ1Q2);
    }
}
