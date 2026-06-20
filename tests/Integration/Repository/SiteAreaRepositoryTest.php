<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Site;
use App\Entity\SiteArea;
use App\Repository\SiteAreaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SiteAreaRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SiteAreaRepository $repository;

    /** Unique prefix per test run to avoid constraint conflicts */
    private string $prefix;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(SiteArea::class);
        $this->prefix = strtoupper(substr(uniqid('SA', true), 0, 8));

        $this->createFixtures();
    }

    private function createFixtures(): void
    {
        $siteFr = $this->createSite("{$this->prefix}_FR_001", 'FR');
        $siteEs = $this->createSite("{$this->prefix}_ES_001", 'ES');

        // FR site: area data for 2023 and 2024, but NOT 2025
        $this->createArea($siteFr, 2023, 1, 1_000.0);
        $this->createArea($siteFr, 2024, 1, 1_200.0);
        $this->createArea($siteFr, 2024, 2, 1_300.0);

        // ES site: area data only for 2023
        $this->createArea($siteEs, 2023, 1, 800.0);

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

    private function createArea(Site $site, int $fiscalYear, int $month, float $salesArea): void
    {
        $area = new SiteArea();
        $area->setSite($site);
        $area->setFiscalYear($fiscalYear);
        $area->setMonth($month);
        $area->setSalesAreaM2($salesArea);
        $this->entityManager->persist($area);
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

    // ── Nominal cases ──────────────────────────────────────────────────────────

    public function testAvgSalesAreaReturnsDataForExactYear(): void
    {
        $results = $this->repository->avgSalesAreaBySiteAndYear(2024);
        $areas = array_column($results, 'avg_sales_area', 'site_unique_code');

        $frKey = "{$this->prefix}_FR_001";
        $this->assertArrayHasKey($frKey, $areas);
        // FR has two 2024 records: 1200 + 1300 → avg = 1250
        $this->assertEqualsWithDelta(1_250.0, (float) $areas[$frKey], 0.01);
    }

    // ── Fallback behaviour (the core of this fix) ──────────────────────────────

    public function testAvgSalesAreaFallsBackToPreviousYearWhenRequestedYearHasNoData(): void
    {
        // 2025 has no records for FR → should fall back to 2024 data
        $results = $this->repository->avgSalesAreaBySiteAndYear(2025);
        $areas = array_column($results, 'avg_sales_area', 'site_unique_code');

        $frKey = "{$this->prefix}_FR_001";
        $this->assertArrayHasKey($frKey, $areas, 'FR should appear even though 2025 has no area data');
        // Falls back to 2024: avg(1200, 1300) = 1250
        $this->assertEqualsWithDelta(1_250.0, (float) $areas[$frKey], 0.01);
    }

    public function testAvgSalesAreaFallsBackAcrossMultipleYears(): void
    {
        // ES only has 2023 data; requesting 2025 must fall back to 2023
        $results = $this->repository->avgSalesAreaBySiteAndYear(2025);
        $areas = array_column($results, 'avg_sales_area', 'site_unique_code');

        $esKey = "{$this->prefix}_ES_001";
        $this->assertArrayHasKey($esKey, $areas, 'ES should appear, falling back from 2025 to 2023');
        $this->assertEqualsWithDelta(800.0, (float) $areas[$esKey], 0.01);
    }

    public function testAvgSalesAreaReturnsNothingWhenAllYearsAreAfterRequested(): void
    {
        // Requesting 2020 → no site has data before 2023 → empty result set
        $results = $this->repository->avgSalesAreaBySiteAndYear(2020);
        $areas = array_column($results, 'avg_sales_area', 'site_unique_code');

        $frKey = "{$this->prefix}_FR_001";
        $esKey = "{$this->prefix}_ES_001";

        $this->assertArrayNotHasKey($frKey, $areas);
        $this->assertArrayNotHasKey($esKey, $areas);
    }

    // ── Country filter ─────────────────────────────────────────────────────────

    public function testAvgSalesAreaFiltersCountryCode(): void
    {
        $results = $this->repository->avgSalesAreaBySiteAndYear(2024, ['FR']);
        $areas = array_column($results, 'avg_sales_area', 'site_unique_code');

        $this->assertArrayHasKey("{$this->prefix}_FR_001", $areas);
        $this->assertArrayNotHasKey("{$this->prefix}_ES_001", $areas);
    }

    public function testAvgSalesAreaWithNullCountryCodesReturnsAll(): void
    {
        $results = $this->repository->avgSalesAreaBySiteAndYear(2024, null);
        $areas = array_column($results, 'avg_sales_area', 'site_unique_code');

        $this->assertArrayHasKey("{$this->prefix}_FR_001", $areas);
    }

    public function testFallbackRespectsCountryFilter(): void
    {
        // 2025 has no data; fallback for FR returns 2024, ES returns 2023
        // Requesting only FR should still return FR (not ES)
        $results = $this->repository->avgSalesAreaBySiteAndYear(2025, ['FR']);
        $areas = array_column($results, 'avg_sales_area', 'site_unique_code');

        $this->assertArrayHasKey("{$this->prefix}_FR_001", $areas);
        $this->assertArrayNotHasKey("{$this->prefix}_ES_001", $areas);
    }
}
