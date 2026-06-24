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

    // ── Multi-country filter tests ─────────────────────────────────────────────

    public function testComputeSummaryPassesCountryCodesToRepositories(): void
    {
        $countryCodes = ['FR', 'ES'];

        $this->energyRepo->expects($this->atLeastOnce())
            ->method('sumByMonthRangeAndResource')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $countryCodes,
            )
            ->willReturn([]);

        $this->areaRepo->expects($this->atLeastOnce())
            ->method('avgSalesAreaBySiteAndYear')
            ->with($this->anything(), $countryCodes)
            ->willReturn([]);

        $this->refrigerantRepo->expects($this->atLeastOnce())
            ->method('sumByMonthRange')
            ->with($this->anything(), $this->anything(), $countryCodes)
            ->willReturn([]);

        $this->service->computeSummary('ELEC', '2025-01', $countryCodes);
    }

    public function testComputeMonthlyEvolutionPassesCountryCodesToRepository(): void
    {
        $countryCodes = ['FR', 'PL'];

        $this->energyRepo->expects($this->atLeastOnce())
            ->method('monthlyTotals')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $countryCodes,
            )
            ->willReturn([]);

        $this->service->computeMonthlyEvolution('ELEC', 2025, $countryCodes);
    }

    public function testComputeSiteRankingPassesCountryCodesToRepositories(): void
    {
        $countryCodes = ['FR'];

        $this->energyRepo->expects($this->atLeastOnce())
            ->method('sumByMonthRangeAndResource')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $countryCodes,
            )
            ->willReturn([]);

        $this->areaRepo->expects($this->atLeastOnce())
            ->method('avgSalesAreaBySiteAndYear')
            ->with($this->anything(), $countryCodes)
            ->willReturn([]);

        $this->service->computeSiteRanking('ELEC', '2025-01', '2025-01', 10, 'DESC', $countryCodes);
    }

    public function testComputeSummaryWithNullCountryCodesPassesNullToRepositories(): void
    {
        $this->energyRepo->expects($this->atLeastOnce())
            ->method('sumByMonthRangeAndResource')
            ->with($this->anything(), $this->anything(), $this->anything(), null)
            ->willReturn([]);

        $this->areaRepo->method('avgSalesAreaBySiteAndYear')->willReturn([]);
        $this->refrigerantRepo->method('sumByMonthRange')->willReturn([]);

        // null = all countries (no filter)
        $this->service->computeSummary('ELEC', '2025-01', null);
    }

    // ── computeCountryIntensity ────────────────────────────────────────────────

    public function testComputeCountryIntensityCalculatesPerCountry(): void
    {
        $this->energyRepo->method('sumByCountryAndMonthRange')->willReturn([
            ['country_code' => 'FR', 'total' => 600_000.0],
            ['country_code' => 'ES', 'total' => 400_000.0],
        ]);
        $this->areaRepo->method('totalSalesAreaByCountryAndYear')->willReturn([
            ['country_code' => 'FR', 'total_sales_area' => 20_000.0],  // 30 kWh/m²
            ['country_code' => 'ES', 'total_sales_area' => 10_000.0],  // 40 kWh/m²
        ]);

        $result = $this->service->computeCountryIntensity('ELEC', '2025-01');

        $this->assertCount(2, $result);
        // Sorted DESC by intensity: ES (40) first, FR (30) second
        $this->assertSame('ES', $result[0]['country_code']);
        $this->assertEqualsWithDelta(40.0, $result[0]['intensity'], 0.01);
        $this->assertSame('FR', $result[1]['country_code']);
        $this->assertEqualsWithDelta(30.0, $result[1]['intensity'], 0.01);
    }

    public function testComputeCountryIntensityReturnsNullIntensityWhenNoArea(): void
    {
        $this->energyRepo->method('sumByCountryAndMonthRange')->willReturn([
            ['country_code' => 'FR', 'total' => 500_000.0],
        ]);
        $this->areaRepo->method('totalSalesAreaByCountryAndYear')->willReturn([]);

        $result = $this->service->computeCountryIntensity('ELEC', '2025-01');

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['intensity']);
        $this->assertSame(500_000.0, $result[0]['total_consumption_kwh']);
    }

    public function testComputeCountryIntensityPassesCountryCodesToRepositories(): void
    {
        $countryCodes = ['FR', 'ES'];

        $this->energyRepo->expects($this->once())
            ->method('sumByCountryAndMonthRange')
            ->with($this->anything(), $this->anything(), $this->anything(), $countryCodes)
            ->willReturn([]);

        $this->areaRepo->expects($this->once())
            ->method('totalSalesAreaByCountryAndYear')
            ->with($this->anything(), $countryCodes)
            ->willReturn([]);

        $this->service->computeCountryIntensity('ELEC', '2025-01', $countryCodes);
    }

    // ── computeRefrigerantByCountry ────────────────────────────────────────────

    public function testComputeRefrigerantByCountryGroupsByCountry(): void
    {
        $this->refrigerantRepo->method('sumByCountryAndMonthRange')->willReturn([
            ['country_code' => 'FR', 'total_kg' => 150.5],
            ['country_code' => 'HU', 'total_kg' => 75.0],
        ]);

        $result = $this->service->computeRefrigerantByCountry('2025-03');

        $this->assertCount(2, $result);
        $this->assertSame('FR', $result[0]['country_code']);
        $this->assertSame(150.5, $result[0]['total_kg']);
        $this->assertSame('HU', $result[1]['country_code']);
    }

    public function testComputeRefrigerantByCountryComputesCorrectQtdRange(): void
    {
        $capturedArgs = [];
        $this->refrigerantRepo->method('sumByCountryAndMonthRange')
            ->willReturnCallback(function (string $from, string $to) use (&$capturedArgs): array {
                $capturedArgs = ['from' => $from, 'to' => $to];
                return [];
            });

        // March 2025 → Q1 → start = 2025-01, end = 2025-03
        $this->service->computeRefrigerantByCountry('2025-03');
        $this->assertSame('2025-01', $capturedArgs['from']);
        $this->assertSame('2025-03', $capturedArgs['to']);

        // July 2025 → Q3 → start = 2025-07, end = 2025-07 (MTD within Q3)
        $this->service->computeRefrigerantByCountry('2025-07');
        $this->assertSame('2025-07', $capturedArgs['from']);
        $this->assertSame('2025-07', $capturedArgs['to']);

        // November 2025 → Q4 → start = 2025-10, end = 2025-11
        $this->service->computeRefrigerantByCountry('2025-11');
        $this->assertSame('2025-10', $capturedArgs['from']);
        $this->assertSame('2025-11', $capturedArgs['to']);
    }

    public function testComputeRefrigerantByCountryIncludesQuarterDatesInResult(): void
    {
        $this->refrigerantRepo->method('sumByCountryAndMonthRange')->willReturn([
            ['country_code' => 'FR', 'total_kg' => 100.0],
        ]);

        $result = $this->service->computeRefrigerantByCountry('2025-02');

        $this->assertSame('2025-01', $result[0]['quarter_start']);
        $this->assertSame('2025-02', $result[0]['quarter_end']);
    }

    // ── computeCountryIntensityMonthly ─────────────────────────────────────────

    public function testComputeCountryIntensityMonthlyReturnsIntensityPerCountryPerMonth(): void
    {
        $this->energyRepo->method('monthlyTotalsByCountry')->willReturn([
            ['country_code' => 'FR', 'month_year' => '2025-01', 'total' => 1_000_000.0],
            ['country_code' => 'FR', 'month_year' => '2025-02', 'total' => 800_000.0],
            ['country_code' => 'ES', 'month_year' => '2025-01', 'total' => 500_000.0],
        ]);
        $this->areaRepo->method('totalSalesAreaByCountryAndYear')->willReturn([
            ['country_code' => 'FR', 'total_sales_area' => 10_000.0],
            ['country_code' => 'ES', 'total_sales_area' => 5_000.0],
        ]);

        $result = $this->service->computeCountryIntensityMonthly('ELEC', 2025);

        $this->assertCount(3, $result);

        $frJan = array_values(array_filter($result, fn($r) => $r['country_code'] === 'FR' && $r['month'] === '2025-01'))[0];
        $this->assertSame(100.0, $frJan['intensity']); // 1_000_000 / 10_000

        $frFeb = array_values(array_filter($result, fn($r) => $r['country_code'] === 'FR' && $r['month'] === '2025-02'))[0];
        $this->assertSame(80.0, $frFeb['intensity']); // 800_000 / 10_000
    }

    public function testComputeCountryIntensityMonthlyReturnsNullIntensityWhenNoArea(): void
    {
        $this->energyRepo->method('monthlyTotalsByCountry')->willReturn([
            ['country_code' => 'FR', 'month_year' => '2025-01', 'total' => 500_000.0],
        ]);
        $this->areaRepo->method('totalSalesAreaByCountryAndYear')->willReturn([]); // no area data

        $result = $this->service->computeCountryIntensityMonthly('ELEC', 2025);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['intensity']);
        $this->assertSame(500_000.0, $result[0]['total_kwh']);
    }

    // ── computeRefrigerantByCountryQuarterly ────────────────────────────────────

    public function testComputeRefrigerantByCountryQuarterlyGroupsByQuarter(): void
    {
        // 2025-05 → current quarter is Q2; so Q1 and Q2 should both appear
        $callLog = [];
        $this->refrigerantRepo->method('monthlyByCountry')
            ->willReturnCallback(function (string $from, string $to) use (&$callLog): array {
                $callLog[] = [$from, $to];
                return [
                    ['country_code' => 'FR', 'month_year' => $from, 'total_kg' => 100.0],
                ];
            });

        $result = $this->service->computeRefrigerantByCountryQuarterly('2025-05');

        // Should have called for Q1 (2025-01 → 2025-03) and Q2 (2025-04 → 2025-05 QTD)
        $this->assertCount(2, $callLog);
        $this->assertSame(['2025-01', '2025-03'], $callLog[0]);
        $this->assertSame(['2025-04', '2025-05'], $callLog[1]);

        // Result: 2 entries (one per quarter), both for FR
        $quarters = array_column($result, 'quarter');
        $this->assertContains('Q1 2025', $quarters);
        $this->assertContains('Q2 2025', $quarters);
    }

    public function testComputeRefrigerantByCountryQuarterlyAggregatesMultipleMonthsInQuarter(): void
    {
        // Only Q1, already completed: 3 months of data for FR → aggregate
        $this->refrigerantRepo->method('monthlyByCountry')->willReturn([
            ['country_code' => 'FR', 'month_year' => '2025-01', 'total_kg' => 100.0],
            ['country_code' => 'FR', 'month_year' => '2025-02', 'total_kg' => 200.0],
            ['country_code' => 'FR', 'month_year' => '2025-03', 'total_kg' => 50.0],
        ]);

        $result = $this->service->computeRefrigerantByCountryQuarterly('2025-03');

        $this->assertCount(1, $result);
        $this->assertSame(350.0, $result[0]['total_kg']); // 100 + 200 + 50
        $this->assertSame('Q1 2025', $result[0]['quarter']);
        $this->assertSame('FR', $result[0]['country_code']);
    }

    // ── computeRefrigerantBreakdown ────────────────────────────────────────────

    public function testComputeRefrigerantBreakdownComputesPercentages(): void
    {
        $this->refrigerantRepo->method('sumByFluidType')->willReturn([
            ['fluid_type' => 'R404A', 'total_kg' => 600.0],
            ['fluid_type' => 'R134a', 'total_kg' => 400.0],
        ]);

        $result = $this->service->computeRefrigerantBreakdown('2025-01', '2025-03');

        $this->assertCount(2, $result);

        $r404a = array_values(array_filter($result, fn($r) => $r['fluid_type'] === 'R404A'))[0];
        $this->assertSame(600.0, $r404a['total_kg']);
        $this->assertSame(60.0, $r404a['percentage']);

        $r134a = array_values(array_filter($result, fn($r) => $r['fluid_type'] === 'R134a'))[0];
        $this->assertSame(400.0, $r134a['total_kg']);
        $this->assertSame(40.0, $r134a['percentage']);
    }

    public function testComputeRefrigerantBreakdownReturnsEmptyWhenNoData(): void
    {
        $this->refrigerantRepo->method('sumByFluidType')->willReturn([]);

        $result = $this->service->computeRefrigerantBreakdown('2025-01', '2025-12');

        $this->assertSame([], $result);
    }
}
