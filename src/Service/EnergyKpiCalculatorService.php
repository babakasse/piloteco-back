<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\EnergyConsumptionRepository;
use App\Repository\RefrigerantFluidRepository;
use App\Repository\SiteAreaRepository;

final readonly class EnergyKpiCalculatorService
{
    public function __construct(
        private EnergyConsumptionRepository $energyConsumptionRepository,
        private SiteAreaRepository $siteAreaRepository,
        private RefrigerantFluidRepository $refrigerantFluidRepository,
    ) {}

    /**
     * KPI Summary for the dashboard (MTD + YTD energy intensity, N vs N-1, refrigerant total).
     *
     * @param array<string>|null $countryCodes
     * @return array{
     *   energy_intensity_mtd: float|null,
     *   energy_intensity_ytd: float|null,
     *   evolution_mtd_vs_n1_percent: float|null,
     *   evolution_ytd_vs_n1_percent: float|null,
     *   total_consumption_mtd: float|null,
     *   total_consumption_ytd: float|null,
     *   refrigerant_total_ytd_kg: float|null,
     * }
     */
    public function computeSummary(
        string $resourceCategory,
        string $currentMonth,
        ?string $countryCode = null,
    ): array {
        $year = (int) substr($currentMonth, 0, 4);
        $ytdStart = sprintf('%d-01', $year);
        $previousMonth = $this->shiftMonth($currentMonth, -12);
        $previousYtdStart = sprintf('%d-01', $year - 1);

        // MTD consumption current year
        $mtdConsumption = $this->sumConsumption($resourceCategory, $currentMonth, $currentMonth, $countryCode);

        // YTD consumption current year
        $ytdConsumption = $this->sumConsumption($resourceCategory, $ytdStart, $currentMonth, $countryCode);

        // MTD consumption N-1
        $mtdConsumptionN1 = $this->sumConsumption($resourceCategory, $previousMonth, $previousMonth, $countryCode);

        // YTD consumption N-1
        $previousYtdEnd = $this->shiftMonth($currentMonth, -12);
        $ytdConsumptionN1 = $this->sumConsumption($resourceCategory, $previousYtdStart, $previousYtdEnd, $countryCode);

        // Sales area for intensity calculation
        $avgSalesArea = $this->getTotalSalesArea($year, $countryCode);
        $avgSalesAreaN1 = $this->getTotalSalesArea($year - 1, $countryCode);

        $intensityMtd = $this->computeIntensity($mtdConsumption, $avgSalesArea);
        $intensityYtd = $this->computeIntensity($ytdConsumption, $avgSalesArea);
        $intensityMtdN1 = $this->computeIntensity($mtdConsumptionN1, $avgSalesAreaN1);
        $intensityYtdN1 = $this->computeIntensity($ytdConsumptionN1, $avgSalesAreaN1);

        // Refrigerant YTD
        $refrigerantYtd = $this->sumRefrigerant($ytdStart, $currentMonth, $countryCode);

        return [
            'energy_intensity_mtd' => $intensityMtd,
            'energy_intensity_ytd' => $intensityYtd,
            'evolution_mtd_vs_n1_percent' => $this->evolutionPercent($intensityMtd, $intensityMtdN1),
            'evolution_ytd_vs_n1_percent' => $this->evolutionPercent($intensityYtd, $intensityYtdN1),
            'total_consumption_mtd' => $mtdConsumption,
            'total_consumption_ytd' => $ytdConsumption,
            'refrigerant_total_ytd_kg' => $refrigerantYtd,
        ];
    }

    /**
     * Monthly data for N vs N-1 bar chart.
     *
     * @return array<array{month: string, current: float|null, previous: float|null}>
     */
    public function computeMonthlyEvolution(
        string $resourceCategory,
        int $year,
        ?string $countryCode = null,
    ): array {
        $currentYearStart = sprintf('%d-01', $year);
        $currentYearEnd = sprintf('%d-12', $year);
        $previousYearStart = sprintf('%d-01', $year - 1);
        $previousYearEnd = sprintf('%d-12', $year - 1);

        $currentRows = $this->energyConsumptionRepository->monthlyTotals(
            $resourceCategory, $currentYearStart, $currentYearEnd, $countryCode,
        );
        $previousRows = $this->energyConsumptionRepository->monthlyTotals(
            $resourceCategory, $previousYearStart, $previousYearEnd, $countryCode,
        );

        $currentByMonth = array_column($currentRows, 'total', 'month_year');
        $previousByMonth = array_column($previousRows, 'total', 'month_year');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $currentKey = sprintf('%d-%02d', $year, $m);
            $previousKey = sprintf('%d-%02d', $year - 1, $m);
            $months[] = [
                'month' => $currentKey,
                'current' => isset($currentByMonth[$currentKey]) ? (float) $currentByMonth[$currentKey] : null,
                'previous' => isset($previousByMonth[$previousKey]) ? (float) $previousByMonth[$previousKey] : null,
            ];
        }

        return $months;
    }

    /**
     * Top/Flop N sites by energy intensity.
     *
     * @return array<array{rank: int, site_unique_code: string, country_code: string, intensity: float, evolution_percent: float|null}>
     */
    public function computeSiteRanking(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        int $limit = 10,
        string $order = 'DESC',
        ?string $countryCode = null,
    ): array {
        $year = (int) substr($monthTo, 0, 4);
        $previousMonthFrom = $this->shiftMonth($monthFrom, -12);
        $previousMonthTo = $this->shiftMonth($monthTo, -12);

        $consumptions = $this->energyConsumptionRepository->sumByMonthRangeAndResource(
            $resourceCategory, $monthFrom, $monthTo, $countryCode,
        );
        $consumptionsN1 = $this->energyConsumptionRepository->sumByMonthRangeAndResource(
            $resourceCategory, $previousMonthFrom, $previousMonthTo, $countryCode,
        );
        $areas = $this->siteAreaRepository->avgSalesAreaBySiteAndYear($year, $countryCode);
        $areasN1 = $this->siteAreaRepository->avgSalesAreaBySiteAndYear($year - 1, $countryCode);

        $consumptionBySite = array_column($consumptions, 'total', 'site_unique_code');
        $consumptionN1BySite = array_column($consumptionsN1, 'total', 'site_unique_code');
        $areaBySite = array_column($areas, 'avg_sales_area', 'site_unique_code');
        $areaN1BySite = array_column($areasN1, 'avg_sales_area', 'site_unique_code');

        $countryBySite = array_column($consumptions, 'country_code', 'site_unique_code');

        $rankings = [];
        foreach ($consumptionBySite as $siteCode => $total) {
            $area = (float) ($areaBySite[$siteCode] ?? 0);
            if ($area <= 0) {
                continue;
            }

            $intensity = (float) $total / $area;
            $totalN1 = isset($consumptionN1BySite[$siteCode]) ? (float) $consumptionN1BySite[$siteCode] : null;
            $areaN1 = isset($areaN1BySite[$siteCode]) ? (float) $areaN1BySite[$siteCode] : null;
            $intensityN1 = ($totalN1 !== null && $areaN1 !== null && $areaN1 > 0)
                ? $totalN1 / $areaN1
                : null;

            $rankings[] = [
                'site_unique_code' => $siteCode,
                'country_code' => $countryBySite[$siteCode] ?? '',
                'intensity' => round($intensity, 3),
                'evolution_percent' => $this->evolutionPercent($intensity, $intensityN1),
            ];
        }

        usort($rankings, static fn (array $a, array $b) => $order === 'DESC'
            ? $b['intensity'] <=> $a['intensity']
            : $a['intensity'] <=> $b['intensity']
        );

        $topN = array_slice($rankings, 0, $limit);

        return array_map(
            static fn (array $site, int $index) => ['rank' => $index + 1, ...$site],
            $topN,
            array_keys($topN),
        );
    }

    private function sumConsumption(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?string $countryCode,
    ): ?float {
        $rows = $this->energyConsumptionRepository->sumByMonthRangeAndResource(
            $resourceCategory, $monthFrom, $monthTo, $countryCode,
        );

        if (empty($rows)) {
            return null;
        }

        return (float) array_sum(array_column($rows, 'total'));
    }

    private function getTotalSalesArea(int $year, ?string $countryCode): ?float
    {
        $areas = $this->siteAreaRepository->avgSalesAreaBySiteAndYear($year, $countryCode);

        if (empty($areas)) {
            return null;
        }

        return (float) array_sum(array_column($areas, 'avg_sales_area'));
    }

    private function sumRefrigerant(string $monthFrom, string $monthTo, ?string $countryCode): ?float
    {
        $rows = $this->refrigerantFluidRepository->sumByMonthRange($monthFrom, $monthTo, $countryCode);

        if (empty($rows)) {
            return null;
        }

        return (float) array_sum(array_column($rows, 'total_kg'));
    }

    private function computeIntensity(?float $consumption, ?float $area): ?float
    {
        if ($consumption === null || $area === null || $area <= 0) {
            return null;
        }

        return round($consumption / $area, 3);
    }

    private function evolutionPercent(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function shiftMonth(string $monthYear, int $monthsOffset): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m', $monthYear);
        if ($date === false) {
            return $monthYear;
        }

        $shifted = $date->modify(sprintf('%+d months', $monthsOffset));
        return $shifted->format('Y-m');
    }
}
