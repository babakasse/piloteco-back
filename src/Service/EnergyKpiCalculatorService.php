<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\EnergyConsumptionRepository;
use App\Repository\RefrigerantFluidRepository;
use App\Repository\SiteAreaRepository;

final readonly class EnergyKpiCalculatorService
{
    /**
     * ELEC sub-categories counted as "green" consumption (renewable energy sourcing).
     * Excludes only "Default retail not supported by EACs" which is conventional grid electricity.
     */
    private const GREEN_CONSUMPTION_SUB_CATEGORIES = [
        'Default retail supported by EACs',
        'Off-site Physical PPA',
        'Project specific contract',
        'Off-site Financial PPA',
        'Lease & operation (as if self-consumption)',
        'Self-consumption (owned & operated)',
        'On-site PPA',
        'Off-site Direct PPA',
        'Unbundled EACs',
        'Retail Green Electricity',
        'Self-consumption (owned)',
    ];

    /**
     * ELEC sub-categories counted as "green production" (on-site / directly operated renewable).
     */
    private const GREEN_PRODUCTION_SUB_CATEGORIES = [
        'Self-consumption (owned & operated)',
        'Lease & operation (as if self-consumption)',
        'On-site PPA',
        'Self-consumption (owned)',
    ];

    public function __construct(
        private EnergyConsumptionRepository $energyConsumptionRepository,
        private SiteAreaRepository $siteAreaRepository,
        private RefrigerantFluidRepository $refrigerantFluidRepository,
    ) {}

    /**
     * KPI Summary for the dashboard.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories  multi-resource override
     * @return array{
     *   energy_intensity_mtd: float|null,
     *   energy_intensity_ytd: float|null,
     *   evolution_mtd_vs_n1_percent: float|null,
     *   evolution_ytd_vs_n1_percent: float|null,
     *   total_consumption_mtd: float|null,
     *   total_consumption_ytd: float|null,
     *   refrigerant_total_ytd_kg: float|null,
     *   sales_surface_m2: float|null,
     *   total_surface_m2: float|null,
     *   commercial_energy_intensity_ytd: float|null,
     *   building_energy_intensity_ytd: float|null,
     *   green_electricity_consumption_kwh: float|null,
     *   green_electricity_consumption_percent: float|null,
     *   green_electricity_production_kwh: float|null,
     *   green_electricity_production_percent: float|null,
     * }
     */
    public function computeSummary(
        string $resourceCategory,
        string $currentMonth,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $year = (int) substr($currentMonth, 0, 4);
        $ytdStart = sprintf('%d-01', $year);
        $previousMonth = $this->shiftMonth($currentMonth, -12);
        $previousYtdStart = sprintf('%d-01', $year - 1);

        $mtdConsumption = $this->sumConsumption(
            $resourceCategory, $currentMonth, $currentMonth, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $ytdConsumption = $this->sumConsumption(
            $resourceCategory, $ytdStart, $currentMonth, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $mtdConsumptionN1 = $this->sumConsumption(
            $resourceCategory, $previousMonth, $previousMonth, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $previousYtdEnd = $this->shiftMonth($currentMonth, -12);
        $ytdConsumptionN1 = $this->sumConsumption(
            $resourceCategory, $previousYtdStart, $previousYtdEnd, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );

        // Sales surface: only MAG sites with non-null consumption for this resource and period
        $magSitesWithConsumption = $this->energyConsumptionRepository->findMagSiteCodesWithConsumption(
            $resourceCategory, $ytdStart, $currentMonth, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $magSitesWithConsumptionN1 = $this->energyConsumptionRepository->findMagSiteCodesWithConsumption(
            $resourceCategory, $previousYtdStart, $this->shiftMonth($currentMonth, -12),
            $countryCodes, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );

        // Pass the exact list (even empty) so that sites without consumption yield null area
        $salesAreaM2 = $this->getTotalSalesArea($year, $countryCodes, siteUniqueCodes: $magSitesWithConsumption);
        $salesAreaM2N1 = $this->getTotalSalesArea($year - 1, $countryCodes, siteUniqueCodes: $magSitesWithConsumptionN1);
        $totalAreaM2 = $this->getTotalBuildingArea($year, $countryCodes);

        $intensityMtd = $this->computeIntensity($mtdConsumption, $salesAreaM2);
        $intensityYtd = $this->computeIntensity($ytdConsumption, $salesAreaM2);
        $intensityMtdN1 = $this->computeIntensity($mtdConsumptionN1, $salesAreaM2N1);
        $intensityYtdN1 = $this->computeIntensity($ytdConsumptionN1, $salesAreaM2N1);
        $buildingIntensityYtd = $this->computeIntensity($ytdConsumption, $totalAreaM2);

        $refrigerantYtd = $this->sumRefrigerant($ytdStart, $currentMonth, $countryCodes, $onlyComparable);

        // Green electricity metrics — always computed from ELEC data regardless of selected resource
        $elecYtdConsumption = $resourceCategory === 'ELEC'
            && ($resourceCategories === null || $resourceCategories === ['ELEC'])
            ? $ytdConsumption
            : $this->sumConsumption('ELEC', $ytdStart, $currentMonth, $countryCodes, null, null, $onlyComparable, $realDataOnly);
        [$greenConsumptionKwh, $greenConsumptionPct, $greenProductionKwh, $greenProductionPct]
            = $this->computeGreenElectricityMetrics(
                $ytdStart, $currentMonth, $countryCodes, $onlyComparable, $elecYtdConsumption, $realDataOnly,
              );

        // CEI = (ELEC + GAS NG) / sales surface — MAG sites only, GAS HN excluded.
        // Both numerator (consumption) and denominator (surface) are restricted to stores.
        $ceiElecYtd = $this->sumConsumption('ELEC', $ytdStart, $currentMonth, $countryCodes, null, null, $onlyComparable, $realDataOnly, ['MAG']);
        $ceiGasNgYtd = $this->sumConsumption('GAS', $ytdStart, $currentMonth, $countryCodes, null, 'NG', $onlyComparable, $realDataOnly, ['MAG']);
        $ceiElecGasYtd = ($ceiElecYtd ?? 0.0) + ($ceiGasNgYtd ?? 0.0) ?: null;
        $commercialEnergyIntensityYtd = $this->computeIntensity($ceiElecGasYtd, $salesAreaM2);

        return [
            'energy_intensity_mtd' => $intensityMtd,
            'energy_intensity_ytd' => $intensityYtd,
            'evolution_mtd_vs_n1_percent' => $this->evolutionPercent($intensityMtd, $intensityMtdN1),
            'evolution_ytd_vs_n1_percent' => $this->evolutionPercent($intensityYtd, $intensityYtdN1),
            'total_consumption_mtd' => $mtdConsumption,
            'total_consumption_ytd' => $ytdConsumption,
            'refrigerant_total_ytd_kg' => $refrigerantYtd,
            'sales_surface_m2' => $salesAreaM2,
            'total_surface_m2' => $totalAreaM2,
            'commercial_energy_intensity_ytd' => $commercialEnergyIntensityYtd,
            'building_energy_intensity_ytd' => $buildingIntensityYtd,
            'green_electricity_consumption_kwh' => $greenConsumptionKwh,
            'green_electricity_consumption_percent' => $greenConsumptionPct,
            'green_electricity_production_kwh' => $greenProductionKwh,
            'green_electricity_production_percent' => $greenProductionPct,
        ];
    }

    /**
     * Monthly data for N vs N-1 bar chart.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return array<array{month: string, current: float|null, previous: float|null}>
     */
    public function computeMonthlyEvolution(
        string $resourceCategory,
        int $year,
        string $currentMonth,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $currentMonthNum = (int) substr($currentMonth, 5, 2);
        $currentYearStart = sprintf('%d-01', $year);
        $currentYearEnd = sprintf('%d-%02d', $year, $currentMonthNum);
        $previousYearStart = sprintf('%d-01', $year - 1);
        $previousYearEnd = sprintf('%d-%02d', $year - 1, $currentMonthNum);

        $currentRows = $this->energyConsumptionRepository->monthlyTotals(
            $resourceCategory, $currentYearStart, $currentYearEnd, $countryCodes,
            null, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $previousRows = $this->energyConsumptionRepository->monthlyTotals(
            $resourceCategory, $previousYearStart, $previousYearEnd, $countryCodes,
            null, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );

        $salesArea = $this->getTotalSalesArea($year, $countryCodes);
        $salesAreaN1 = $this->getTotalSalesArea($year - 1, $countryCodes);

        $currentByMonth = array_column($currentRows, 'total', 'month_year');
        $previousByMonth = array_column($previousRows, 'total', 'month_year');

        $months = [];
        $cumulativeCurrent = 0.0;
        $cumulativePrevious = 0.0;

        for ($m = 1; $m <= $currentMonthNum; $m++) {
            $currentKey = sprintf('%d-%02d', $year, $m);
            $previousKey = sprintf('%d-%02d', $year - 1, $m);

            $cumulativeCurrent += (float) ($currentByMonth[$currentKey] ?? 0);
            $cumulativePrevious += (float) ($previousByMonth[$previousKey] ?? 0);

            $intensityCurrent = $this->computeIntensity($cumulativeCurrent, $salesArea);
            $intensityPrevious = $this->computeIntensity($cumulativePrevious, $salesAreaN1);

            $months[] = [
                'month' => $currentKey,
                'current' => $intensityCurrent,
                'previous' => $intensityPrevious,
                'evolutionPercent' => $this->evolutionPercent($intensityCurrent, $intensityPrevious),
            ];
        }

        return $months;
    }

    /**
     * Consumption summary for the Energy Efficiency page.
     * Always returns two buckets: all-site-type and MAG-only,
     * broken down by resource (ELEC, GAS NG, GAS HN, WATER CONSUMED, WATER STORED).
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $siteTypes   filter for the charts (not applied to the two fixed buckets)
     * @param list<string>|null $siteFormats filter for the charts
     * @return array{
     *   all: array{elec_kwh: float|null, gas_ng_kwh: float|null, gas_hn_kwh: float|null, elec_gas_kwh: float|null, water_consumed_m3: float|null, water_stored_m3: float|null},
     *   mag: array{elec_kwh: float|null, gas_ng_kwh: float|null, gas_hn_kwh: float|null, elec_gas_kwh: float|null, water_consumed_m3: float|null, water_stored_m3: float|null},
     * }
     */
    public function computeEfficiencySummary(
        string $currentMonth,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $year = (int) substr($currentMonth, 0, 4);
        $ytdStart = sprintf('%d-01', $year);

        $bucket = function (?array $siteTypesFilter, string $fromMonth) use ($currentMonth, $countryCodes, $onlyComparable, $realDataOnly): array {
            $elec = $this->sumConsumption('ELEC', $fromMonth, $currentMonth, $countryCodes, null, null, $onlyComparable, $realDataOnly, $siteTypesFilter);
            $gasNg = $this->sumConsumption('GAS', $fromMonth, $currentMonth, $countryCodes, null, 'NG', $onlyComparable, $realDataOnly, $siteTypesFilter);
            $gasHn = $this->sumConsumption('GAS', $fromMonth, $currentMonth, $countryCodes, null, 'HN', $onlyComparable, $realDataOnly, $siteTypesFilter);
            $waterConsumed = $this->sumConsumption('WATER', $fromMonth, $currentMonth, $countryCodes, null, 'CONSUMED', $onlyComparable, $realDataOnly, $siteTypesFilter);
            $waterStored = $this->sumConsumption('WATER', $fromMonth, $currentMonth, $countryCodes, null, 'STORED', $onlyComparable, $realDataOnly, $siteTypesFilter);

            $elecGas = ($elec !== null || $gasNg !== null || $gasHn !== null)
                ? ($elec ?? 0.0) + ($gasNg ?? 0.0) + ($gasHn ?? 0.0)
                : null;

            return [
                'elec_kwh' => $elec,
                'gas_ng_kwh' => $gasNg,
                'gas_hn_kwh' => $gasHn,
                'elec_gas_kwh' => $elecGas,
                'water_consumed_m3' => $waterConsumed,
                'water_stored_m3' => $waterStored,
            ];
        };

        return [
            'ytd' => [
                'all' => $bucket(null, $ytdStart),
                'mag' => $bucket(['MAG'], $ytdStart),
            ],
            'mtd' => [
                'all' => $bucket(null, $currentMonth),
                'mag' => $bucket(['MAG'], $currentMonth),
            ],
        ];
    }

    /**
     * Monthly intensity N vs N-1 for the 4 energy efficiency charts.
     *
     * @param 'sales'|'total' $surfaceType  which surface area to use
     * @param 'ytd'|'mtd'     $mode         ytd = cumulative, mtd = monthly only
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{month: string, current: float|null, previous: float|null, evolutionPercent: float|null}>
     */
    public function computeMonthlyIntensity(
        string $resourceCategory,
        int $year,
        string $currentMonth,
        string $surfaceType = 'sales',
        string $mode = 'ytd',
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): array {
        $currentMonthNum = (int) substr($currentMonth, 5, 2);
        $currentYearStart = sprintf('%d-01', $year);
        $currentYearEnd = sprintf('%d-%02d', $year, $currentMonthNum);
        $previousYearStart = sprintf('%d-01', $year - 1);
        $previousYearEnd = sprintf('%d-%02d', $year - 1, $currentMonthNum);

        $currentRows = $this->energyConsumptionRepository->monthlyTotals(
            $resourceCategory, $currentYearStart, $currentYearEnd, $countryCodes,
            null, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
            $siteTypes, $siteFormats,
        );
        $previousRows = $this->energyConsumptionRepository->monthlyTotals(
            $resourceCategory, $previousYearStart, $previousYearEnd, $countryCodes,
            null, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
            $siteTypes, $siteFormats,
        );

        if ($surfaceType === 'total') {
            $areaCurrent = $this->getTotalBuildingArea($year, $countryCodes, $siteTypes, $siteFormats);
            $areaPrevious = $this->getTotalBuildingArea($year - 1, $countryCodes, $siteTypes, $siteFormats);
        } else {
            $areaCurrent = $this->getTotalSalesArea($year, $countryCodes, $siteTypes, $siteFormats);
            $areaPrevious = $this->getTotalSalesArea($year - 1, $countryCodes, $siteTypes, $siteFormats);
        }

        $currentByMonth = array_column($currentRows, 'total', 'month_year');
        $previousByMonth = array_column($previousRows, 'total', 'month_year');

        $months = [];
        $cumulativeCurrent = 0.0;
        $cumulativePrevious = 0.0;

        for ($m = 1; $m <= $currentMonthNum; $m++) {
            $currentKey = sprintf('%d-%02d', $year, $m);
            $previousKey = sprintf('%d-%02d', $year - 1, $m);

            $monthCurrent = (float) ($currentByMonth[$currentKey] ?? 0);
            $monthPrevious = (float) ($previousByMonth[$previousKey] ?? 0);

            if ($mode === 'ytd') {
                $cumulativeCurrent += $monthCurrent;
                $cumulativePrevious += $monthPrevious;
                $intensityCurrent = $this->computeIntensity($cumulativeCurrent, $areaCurrent);
                $intensityPrevious = $this->computeIntensity($cumulativePrevious, $areaPrevious);
            } else {
                $intensityCurrent = $this->computeIntensity($monthCurrent > 0 ? $monthCurrent : null, $areaCurrent);
                $intensityPrevious = $this->computeIntensity($monthPrevious > 0 ? $monthPrevious : null, $areaPrevious);
            }

            $months[] = [
                'month' => $currentKey,
                'current' => $intensityCurrent,
                'previous' => $intensityPrevious,
                'evolutionPercent' => $this->evolutionPercent($intensityCurrent, $intensityPrevious),
            ];
        }

        return $months;
    }

    /**
     * Top/Flop N sites by energy intensity.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return array<array{rank: int, site_unique_code: string, country_code: string, intensity: float, evolution_percent: float|null}>
     */
    public function computeSiteRanking(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        int $limit = 10,
        string $order = 'DESC',
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $year = (int) substr($monthTo, 0, 4);
        $previousMonthFrom = $this->shiftMonth($monthFrom, -12);
        $previousMonthTo = $this->shiftMonth($monthTo, -12);

        $consumptions = $this->energyConsumptionRepository->sumByMonthRangeAndResource(
            $resourceCategory, $monthFrom, $monthTo, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $consumptionsN1 = $this->energyConsumptionRepository->sumByMonthRangeAndResource(
            $resourceCategory, $previousMonthFrom, $previousMonthTo, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $areas = $this->siteAreaRepository->avgSalesAreaBySiteAndYear($year, $countryCodes);
        $areasN1 = $this->siteAreaRepository->avgSalesAreaBySiteAndYear($year - 1, $countryCodes);

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

    /**
     * Energy intensity per country for the selected month (MTD).
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return array<array{country_code: string, intensity: float|null, total_consumption_kwh: float, total_area_m2: float|null}>
     */
    public function computeCountryIntensity(
        string $resourceCategory,
        string $currentMonth,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $year = (int) substr($currentMonth, 0, 4);

        $consumptions = $this->energyConsumptionRepository->sumByCountryAndMonthRange(
            $resourceCategory, $currentMonth, $currentMonth, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $areas = $this->siteAreaRepository->totalSalesAreaByCountryAndYear($year, $countryCodes, onlyMag: true);

        $consumptionByCountry = array_column($consumptions, 'total', 'country_code');
        $areaByCountry = array_column($areas, 'total_sales_area', 'country_code');

        $results = [];
        foreach ($consumptionByCountry as $countryCode => $total) {
            $area = isset($areaByCountry[$countryCode]) ? (float) $areaByCountry[$countryCode] : null;
            $intensity = ($area !== null && $area > 0)
                ? round((float) $total / $area, 3)
                : null;

            $results[] = [
                'country_code' => (string) $countryCode,
                'total_consumption_kwh' => (float) $total,
                'total_area_m2' => $area,
                'intensity' => $intensity,
            ];
        }

        usort($results, static fn (array $a, array $b): int => ($b['intensity'] ?? 0) <=> ($a['intensity'] ?? 0));

        return $results;
    }

    /**
     * Monthly energy intensity per country (YTD) — for the country intensity YTD chart.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return array<array{month: string, country_code: string, intensity: float|null, total_kwh: float}>
     */
    public function computeCountryIntensityMonthly(
        string $resourceCategory,
        int $year,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $yearStart = sprintf('%d-01', $year);
        $yearEnd = sprintf('%d-12', $year);

        $rows = $this->energyConsumptionRepository->monthlyTotalsByCountry(
            $resourceCategory, $yearStart, $yearEnd, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
        );
        $areas = $this->siteAreaRepository->totalSalesAreaByCountryAndYear($year, $countryCodes, onlyMag: true);
        $areaByCountry = array_column($areas, 'total_sales_area', 'country_code');

        $results = [];
        foreach ($rows as $row) {
            $countryCode = (string) $row['country_code'];
            $total = (float) $row['total'];
            $area = isset($areaByCountry[$countryCode]) ? (float) $areaByCountry[$countryCode] : null;
            $intensity = ($area !== null && $area > 0)
                ? round($total / $area, 3)
                : null;

            $results[] = [
                'month' => (string) $row['month_year'],
                'country_code' => $countryCode,
                'intensity' => $intensity,
                'total_kwh' => $total,
            ];
        }

        return $results;
    }

    /**
     * Refrigerant fluid reloads per country for the quarter containing the current month (QTD).
     *
     * @param list<string>|null $countryCodes
     * @return array<array{country_code: string, total_kg: float, quarter_start: string, quarter_end: string}>
     */
    public function computeRefrigerantByCountry(
        string $currentMonth,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
    ): array {
        [$qtdStart, $qtdEnd] = $this->currentQuarterRange($currentMonth);

        $rows = $this->refrigerantFluidRepository->sumByCountryAndMonthRange(
            $qtdStart, $qtdEnd, $countryCodes, $onlyComparable,
        );

        return array_map(
            static fn (array $row): array => [
                'country_code' => (string) $row['country_code'],
                'total_kg' => (float) $row['total_kg'],
                'quarter_start' => $qtdStart,
                'quarter_end' => $qtdEnd,
            ],
            $rows,
        );
    }

    /**
     * Monthly refrigerant reloads per country, quarter by quarter (YTD) — for the quarterly YTD chart.
     *
     * Returns one entry per (quarter, country) pair for all quarters up to the current month in the given year.
     *
     * @param list<string>|null $countryCodes
     * @return array<array{quarter: string, quarter_start: string, quarter_end: string, country_code: string, total_kg: float}>
     */
    public function computeRefrigerantByCountryQuarterly(
        string $currentMonth,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
    ): array {
        $year = (int) substr($currentMonth, 0, 4);
        $currentMonthInt = (int) substr($currentMonth, 5, 2);
        $currentQuarterNum = (int) ceil($currentMonthInt / 3);

        $results = [];
        for ($q = 1; $q <= $currentQuarterNum; $q++) {
            $qStartMonth = ($q - 1) * 3 + 1;
            $qEndMonth = $q * 3;
            $qStart = sprintf('%d-%02d', $year, $qStartMonth);
            $qEnd = $q === $currentQuarterNum
                ? $currentMonth
                : sprintf('%d-%02d', $year, $qEndMonth);

            $rows = $this->refrigerantFluidRepository->monthlyByCountry(
                $qStart, $qEnd, $countryCodes, $onlyComparable,
            );

            $totalByCountry = [];
            foreach ($rows as $row) {
                $cc = (string) $row['country_code'];
                $totalByCountry[$cc] = ($totalByCountry[$cc] ?? 0.0) + (float) $row['total_kg'];
            }

            foreach ($totalByCountry as $cc => $total) {
                $results[] = [
                    'quarter' => sprintf('Q%d %d', $q, $year),
                    'quarter_start' => $qStart,
                    'quarter_end' => $qEnd,
                    'country_code' => $cc,
                    'total_kg' => round($total, 3),
                ];
            }
        }

        return $results;
    }

    /**
     * Refrigerant breakdown by fluid type (for pie chart).
     *
     * @param list<string>|null $countryCodes
     * @return array<array{fluid_type: string, total_kg: float, percentage: float}>
     */
    public function computeRefrigerantBreakdown(
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
    ): array {
        $rows = $this->refrigerantFluidRepository->sumByFluidType(
            $monthFrom, $monthTo, $countryCodes, $onlyComparable,
        );

        $grandTotal = (float) array_sum(array_column($rows, 'total_kg'));

        return array_map(
            static fn (array $row) => [
                'fluid_type' => (string) $row['fluid_type'],
                'total_kg' => (float) $row['total_kg'],
                'percentage' => $grandTotal > 0
                    ? round((float) $row['total_kg'] / $grandTotal * 100, 2)
                    : 0.0,
            ],
            $rows,
        );
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @return array{string, string}
     */
    private function currentQuarterRange(string $monthYear): array
    {
        $year = (int) substr($monthYear, 0, 4);
        $month = (int) substr($monthYear, 5, 2);
        $quarterStartMonth = (int) (floor(($month - 1) / 3) * 3) + 1;

        return [
            sprintf('%d-%02d', $year, $quarterStartMonth),
            $monthYear,
        ];
    }

    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     */
    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     */
    private function sumConsumption(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): ?float {
        $rows = $this->energyConsumptionRepository->sumByMonthRangeAndResource(
            $resourceCategory, $monthFrom, $monthTo, $countryCodes,
            $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly,
            $siteTypes, $siteFormats,
        );

        if (empty($rows)) {
            return null;
        }

        return (float) array_sum(array_column($rows, 'total'));
    }

    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     */
    private function getTotalSalesArea(
        int $year,
        ?array $countryCodes,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
        ?array $siteUniqueCodes = null,
    ): ?float {
        $areas = $this->siteAreaRepository->avgSalesAreaBySiteAndYear($year, $countryCodes, onlyMag: true, siteTypes: $siteTypes, siteFormats: $siteFormats, siteUniqueCodes: $siteUniqueCodes);

        if (empty($areas)) {
            return null;
        }

        return (float) array_sum(array_column($areas, 'avg_sales_area'));
    }

    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     */
    private function getTotalBuildingArea(
        int $year,
        ?array $countryCodes,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): ?float {
        $areas = $this->siteAreaRepository->avgTotalAreaBySiteAndYear($year, $countryCodes, siteTypes: $siteTypes, siteFormats: $siteFormats);

        if (empty($areas)) {
            return null;
        }

        return (float) array_sum(array_column($areas, 'avg_total_area'));
    }

    /**
     * Compute green electricity KPIs (consumption + production) as absolute kWh and % of total YTD.
     *
     * @param list<string>|null $countryCodes
     * @return array{float|null, float|null, float|null, float|null}
     *   [greenConsumptionKwh, greenConsumptionPct, greenProductionKwh, greenProductionPct]
     */
    private function computeGreenElectricityMetrics(
        string $ytdStart,
        string $currentMonth,
        ?array $countryCodes,
        ?bool $onlyComparable,
        ?float $totalYtdConsumption,
        ?bool $realDataOnly = null,
    ): array {
        if ($totalYtdConsumption === null || $totalYtdConsumption <= 0) {
            return [null, null, null, null];
        }

        $greenConsumption = $this->energyConsumptionRepository->sumByMonthRangeAndSubCategories(
            $ytdStart, $currentMonth,
            self::GREEN_CONSUMPTION_SUB_CATEGORIES,
            $countryCodes, $onlyComparable, $realDataOnly,
        );

        $greenProduction = $this->energyConsumptionRepository->sumByMonthRangeAndSubCategories(
            $ytdStart, $currentMonth,
            self::GREEN_PRODUCTION_SUB_CATEGORIES,
            $countryCodes, $onlyComparable, $realDataOnly,
        );

        $greenConsumptionPct = round(($greenConsumption / $totalYtdConsumption) * 100, 1);
        $greenProductionPct = round(($greenProduction / $totalYtdConsumption) * 100, 1);

        return [
            $greenConsumption > 0 ? $greenConsumption : null,
            $greenConsumption > 0 ? $greenConsumptionPct : null,
            $greenProduction > 0 ? $greenProduction : null,
            $greenProduction > 0 ? $greenProductionPct : null,
        ];
    }

    /**
     * @param list<string>|null $countryCodes
     */
    private function sumRefrigerant(
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes,
        ?bool $onlyComparable = null,
    ): ?float {
        $rows = $this->refrigerantFluidRepository->sumByMonthRange(
            $monthFrom, $monthTo, $countryCodes, $onlyComparable,
        );

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
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $monthYear);
        if ($date === false) {
            return $monthYear;
        }

        $shifted = $date->modify(sprintf('%+d months', $monthsOffset));
        return $shifted->format('Y-m');
    }
}
