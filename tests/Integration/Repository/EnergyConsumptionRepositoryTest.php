<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\EnergyConsumption;
use App\Entity\Site;
use App\Repository\EnergyConsumptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EnergyConsumptionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private EnergyConsumptionRepository $repository;

    /** Unique prefix per test run to avoid constraint conflicts */
    private string $prefix;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(EnergyConsumption::class);
        $this->prefix = strtoupper(substr(uniqid('T', true), 0, 8));

        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        $siteFr = $this->createSite("{$this->prefix}_FR_001", 'FR');
        $siteEs = $this->createSite("{$this->prefix}_ES_001", 'ES');

        // FR site: 3 months ELEC
        $this->createConsumption($siteFr, '2024-01', 'ELEC', 100_000.0);
        $this->createConsumption($siteFr, '2024-02', 'ELEC', 120_000.0);
        $this->createConsumption($siteFr, '2024-03', 'ELEC', 110_000.0);

        // ES site: 2 months ELEC
        $this->createConsumption($siteEs, '2024-01', 'ELEC', 80_000.0);
        $this->createConsumption($siteEs, '2024-02', 'ELEC', 90_000.0);

        // GAS data (should NOT appear in ELEC queries)
        $this->createConsumption($siteFr, '2024-01', 'GAS', 50_000.0);

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

    private function createConsumption(Site $site, string $monthYear, string $resource, float $total): void
    {
        $consumption = new EnergyConsumption();
        $consumption->setSite($site);
        $consumption->setMonthYear($monthYear);
        $consumption->setResourceCategory($resource);
        $consumption->setTotalSurfaceQuantityConsumed($total);
        $this->entityManager->persist($consumption);
    }

    protected function tearDown(): void
    {
        // DAMA DoctrineTestBundle wraps tests in a transaction, but to be safe
        // we also clean up manually to handle cases where DAMA is not rolling back.
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

    public function testSumByMonthRangeReturnsAllSites(): void
    {
        $results = $this->repository->sumByMonthRangeAndResource('ELEC', '2024-01', '2024-01');

        $totals = array_column($results, 'total', 'site_unique_code');

        $frKey = "{$this->prefix}_FR_001";
        $esKey = "{$this->prefix}_ES_001";

        $this->assertArrayHasKey($frKey, $totals);
        $this->assertArrayHasKey($esKey, $totals);
        $this->assertSame(100_000.0, (float) $totals[$frKey]);
        $this->assertSame(80_000.0, (float) $totals[$esKey]);
    }

    public function testSumByMonthRangeFiltersResourceCategory(): void
    {
        $results = $this->repository->sumByMonthRangeAndResource('GAS', '2024-01', '2024-01');
        $totals = array_column($results, 'total', 'site_unique_code');

        $frKey = "{$this->prefix}_FR_001";
        $esKey = "{$this->prefix}_ES_001";

        $this->assertArrayHasKey($frKey, $totals);
        $this->assertArrayNotHasKey($esKey, $totals);
        $this->assertSame(50_000.0, (float) $totals[$frKey]);
    }

    public function testSumByMonthRangeAggregatesMultipleMonths(): void
    {
        // Jan + Feb + Mar for FR = 100k + 120k + 110k = 330k
        $results = $this->repository->sumByMonthRangeAndResource('ELEC', '2024-01', '2024-03');
        $totals = array_column($results, 'total', 'site_unique_code');

        $frKey = "{$this->prefix}_FR_001";
        $this->assertArrayHasKey($frKey, $totals);
        $this->assertSame(330_000.0, (float) $totals[$frKey]);
    }

    public function testMonthlyTotalsReturnsOrderedData(): void
    {
        $results = $this->repository->monthlyTotals('ELEC', '2024-01', '2024-12');
        $months = array_column($results, 'month_year');

        $this->assertContains('2024-01', $months);
        $this->assertContains('2024-02', $months);

        $pos01 = array_search('2024-01', $months, true);
        $pos02 = array_search('2024-02', $months, true);
        $this->assertLessThan($pos02, $pos01, 'Months must be in ascending order');
    }

    public function testFindBySiteMonthAndResourceReturnsNullForUnknownMonth(): void
    {
        $site = $this->entityManager->getRepository(Site::class)
            ->findOneBy(['siteUniqueCode' => "{$this->prefix}_FR_001"]);

        $result = $this->repository->findBySiteMonthAndResource($site, '2099-01', 'ELEC', null);

        $this->assertNull($result);
    }

    public function testFindBySiteMonthAndResourceFindsExistingRecord(): void
    {
        $site = $this->entityManager->getRepository(Site::class)
            ->findOneBy(['siteUniqueCode' => "{$this->prefix}_FR_001"]);

        $result = $this->repository->findBySiteMonthAndResource($site, '2024-01', 'ELEC', null);

        $this->assertNotNull($result);
        $this->assertSame(100_000.0, $result->getTotalSurfaceQuantityConsumed());
    }

    // ── Multi-country filter tests ─────────────────────────────────────────────

    public function testSumByMonthRangeFiltersBySingleCountryCode(): void
    {
        $results = $this->repository->sumByMonthRangeAndResource('ELEC', '2024-01', '2024-01', ['FR']);
        $codes = array_column($results, 'site_unique_code');

        $this->assertContains("{$this->prefix}_FR_001", $codes);
        $this->assertNotContains("{$this->prefix}_ES_001", $codes);
    }

    public function testSumByMonthRangeFiltersMultipleCountryCodes(): void
    {
        // Both FR and ES are in the filter → both returned
        $results = $this->repository->sumByMonthRangeAndResource('ELEC', '2024-01', '2024-01', ['FR', 'ES']);
        $codes = array_column($results, 'site_unique_code');

        $this->assertContains("{$this->prefix}_FR_001", $codes);
        $this->assertContains("{$this->prefix}_ES_001", $codes);
    }

    public function testSumByMonthRangeWithEmptyCountryCodesReturnsAll(): void
    {
        // null = no filter → all countries
        $results = $this->repository->sumByMonthRangeAndResource('ELEC', '2024-01', '2024-01', null);
        $codes = array_column($results, 'site_unique_code');

        $this->assertContains("{$this->prefix}_FR_001", $codes);
        $this->assertContains("{$this->prefix}_ES_001", $codes);
    }

    public function testSumByMonthRangeWithUnknownCountryReturnsNothing(): void
    {
        $results = $this->repository->sumByMonthRangeAndResource('ELEC', '2024-01', '2024-01', ['ZZ']);

        $codes = array_column($results, 'site_unique_code');
        $this->assertNotContains("{$this->prefix}_FR_001", $codes);
        $this->assertNotContains("{$this->prefix}_ES_001", $codes);
    }

    public function testMonthlyTotalsFiltersByMultipleCountryCodes(): void
    {
        // Only FR → ES data excluded
        $resultsFr = $this->repository->monthlyTotals('ELEC', '2024-01', '2024-12', ['FR']);
        $totalFr = array_sum(array_column($resultsFr, 'total'));

        // Both countries → higher total
        $resultsBoth = $this->repository->monthlyTotals('ELEC', '2024-01', '2024-12', ['FR', 'ES']);
        $totalBoth = array_sum(array_column($resultsBoth, 'total'));

        $this->assertGreaterThan($totalFr, $totalBoth, 'Adding ES to filter must increase total consumption');
    }
}
