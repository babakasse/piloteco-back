<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\EnergyConsumptionRepository;
use App\Repository\RefrigerantFluidRepository;
use App\Repository\SiteAreaRepository;
use App\Service\EnergyKpiCalculatorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnergyKpiCalculatorServiceTest extends TestCase
{
    private EnergyConsumptionRepository&MockObject $energyRepo;
    private SiteAreaRepository&MockObject $areaRepo;
    private RefrigerantFluidRepository&MockObject $refrigerantRepo;
    private EnergyKpiCalculatorService $service;

    protected function setUp(): void
    {
        $this->energyRepo = $this->createMock(EnergyConsumptionRepository::class);
        $this->areaRepo = $this->createMock(SiteAreaRepository::class);
        $this->refrigerantRepo = $this->createMock(RefrigerantFluidRepository::class);

        $this->service = new EnergyKpiCalculatorService(
            $this->energyRepo,
            $this->areaRepo,
            $this->refrigerantRepo,
        );
    }

    public function testComputeSummaryReturnsCorrectIntensity(): void
    {
        // 2 sites: 1 000 000 kWh total, 10 000 m² total → 100 kWh/m²
        $this->energyRepo->method('sumByMonthRangeAndResource')->willReturn([
            ['site_unique_code' => 'FR_001_MAG', 'country_code' => 'FR', 'total' => 600_000.0],
            ['site_unique_code' => 'ES_001_MAG', 'country_code' => 'ES', 'total' => 400_000.0],
        ]);
        $this->areaRepo->method('avgSalesAreaBySiteAndYear')->willReturn([
            ['site_unique_code' => 'FR_001_MAG', 'avg_sales_area' => 6_000.0],
            ['site_unique_code' => 'ES_001_MAG', 'avg_sales_area' => 4_000.0],
        ]);
        $this->refrigerantRepo->method('sumByMonthRange')->willReturn([
            ['month_year' => '2025-01', 'total_kg' => 250.5],
        ]);

        $result = $this->service->computeSummary('ELEC', '2025-01');

        $this->assertSame(100.0, $result['energy_intensity_mtd']);
        $this->assertSame(100.0, $result['energy_intensity_ytd']);
        $this->assertSame(1_000_000.0, $result['total_consumption_mtd']);
        $this->assertSame(250.5, $result['refrigerant_total_ytd_kg']);
    }

    public function testComputeSummaryEvolutionVsN1(): void
    {
        $callCount = 0;

        // Current year: 1 000 000 kWh / 10 000 m² = 100 kWh/m²
        // Previous year: 1 100 000 kWh / 10 000 m² = 110 kWh/m²
        // Evolution = (100 - 110) / 110 * 100 = -9.09%
        $this->energyRepo->method('sumByMonthRangeAndResource')
            ->willReturnCallback(function () use (&$callCount): array {
                $callCount++;
                // First 2 calls = current year (mtd + ytd aggregated differently)
                // Since mtd=ytd for Jan, same totals → toggle between current and N-1
                return match ($callCount) {
                    1, 2 => [['site_unique_code' => 'FR_001_MAG', 'country_code' => 'FR', 'total' => 1_000_000.0]],
                    default => [['site_unique_code' => 'FR_001_MAG', 'country_code' => 'FR', 'total' => 1_100_000.0]],
                };
            });

        $areaCallCount = 0;
        $this->areaRepo->method('avgSalesAreaBySiteAndYear')
            ->willReturnCallback(function () use (&$areaCallCount): array {
                $areaCallCount++;
                return [['site_unique_code' => 'FR_001_MAG', 'avg_sales_area' => 10_000.0]];
            });

        $this->refrigerantRepo->method('sumByMonthRange')->willReturn([]);

        $result = $this->service->computeSummary('ELEC', '2025-01');

        $this->assertNotNull($result['evolution_mtd_vs_n1_percent']);
        // Current 100 vs N-1 110 → improvement (negative evolution = good)
        $evolution = $result['evolution_mtd_vs_n1_percent'];
        $this->assertLessThan(0, $evolution, 'Evolution should be negative when consumption decreased');
    }

    public function testComputeSummaryReturnsNullIntensityWhenNoArea(): void
    {
        $this->energyRepo->method('sumByMonthRangeAndResource')->willReturn([
            ['site_unique_code' => 'FR_001_MAG', 'country_code' => 'FR', 'total' => 500_000.0],
        ]);
        $this->areaRepo->method('avgSalesAreaBySiteAndYear')->willReturn([]);
        $this->refrigerantRepo->method('sumByMonthRange')->willReturn([]);

        $result = $this->service->computeSummary('ELEC', '2025-01');

        $this->assertNull($result['energy_intensity_mtd']);
        $this->assertNull($result['evolution_mtd_vs_n1_percent']);
    }

    public function testComputeSummaryReturnsNullWhenNoData(): void
    {
        $this->energyRepo->method('sumByMonthRangeAndResource')->willReturn([]);
        $this->areaRepo->method('avgSalesAreaBySiteAndYear')->willReturn([]);
        $this->refrigerantRepo->method('sumByMonthRange')->willReturn([]);

        $result = $this->service->computeSummary('ELEC', '2025-01');

        $this->assertNull($result['energy_intensity_mtd']);
        $this->assertNull($result['total_consumption_mtd']);
        $this->assertNull($result['refrigerant_total_ytd_kg']);
    }

    public function testComputeMonthlyEvolutionReturns12Months(): void
    {
        $this->energyRepo->method('monthlyTotals')->willReturn([
            ['month_year' => '2024-01', 'total' => 1_000_000.0],
            ['month_year' => '2024-06', 'total' => 900_000.0],
        ]);

        $result = $this->service->computeMonthlyEvolution('ELEC', 2024);

        $this->assertCount(12, $result);
        $this->assertSame('2024-01', $result[0]['month']);
        $this->assertSame('2024-12', $result[11]['month']);
        $this->assertSame(1_000_000.0, $result[0]['current']);
        $this->assertNull($result[0]['previous']); // No N-1 data mocked
        $this->assertNull($result[1]['current']); // Feb has no data
    }

    public function testComputeSiteRankingOrdersDescByDefault(): void
    {
        $this->energyRepo->method('sumByMonthRangeAndResource')->willReturn([
            ['site_unique_code' => 'FR_001_MAG', 'country_code' => 'FR', 'total' => 500_000.0],
            ['site_unique_code' => 'FR_002_MAG', 'country_code' => 'FR', 'total' => 200_000.0],
            ['site_unique_code' => 'ES_001_MAG', 'country_code' => 'ES', 'total' => 800_000.0],
        ]);
        $this->areaRepo->method('avgSalesAreaBySiteAndYear')->willReturn([
            ['site_unique_code' => 'FR_001_MAG', 'avg_sales_area' => 5_000.0],  // 100 kWh/m²
            ['site_unique_code' => 'FR_002_MAG', 'avg_sales_area' => 10_000.0], // 20 kWh/m²
            ['site_unique_code' => 'ES_001_MAG', 'avg_sales_area' => 4_000.0],  // 200 kWh/m²
        ]);

        $result = $this->service->computeSiteRanking('ELEC', '2025-01', '2025-01', 3, 'DESC');

        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]['rank']);
        $this->assertSame('ES_001_MAG', $result[0]['site_unique_code']);
        $this->assertSame(200.0, $result[0]['intensity']);
        $this->assertSame(2, $result[1]['rank']);
        $this->assertSame('FR_001_MAG', $result[1]['site_unique_code']);
    }

    public function testComputeSiteRankingExcludesSitesWithoutArea(): void
    {
        $this->energyRepo->method('sumByMonthRangeAndResource')->willReturn([
            ['site_unique_code' => 'FR_001_MAG', 'country_code' => 'FR', 'total' => 500_000.0],
            ['site_unique_code' => 'FR_NOSURF_MAG', 'country_code' => 'FR', 'total' => 900_000.0],
        ]);
        $this->areaRepo->method('avgSalesAreaBySiteAndYear')->willReturn([
            ['site_unique_code' => 'FR_001_MAG', 'avg_sales_area' => 5_000.0],
            // FR_NOSURF_MAG has no area data
        ]);

        $result = $this->service->computeSiteRanking('ELEC', '2025-01', '2025-01', 10, 'DESC');

        $this->assertCount(1, $result);
        $this->assertSame('FR_001_MAG', $result[0]['site_unique_code']);
    }
}
