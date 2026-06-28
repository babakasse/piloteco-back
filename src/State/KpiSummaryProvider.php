<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiSummaryResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiSummaryResource>
 */
final readonly class KpiSummaryProvider implements ProviderInterface
{
    use KpiFilterResolverTrait;

    public function __construct(
        private EnergyKpiCalculatorService $kpiCalculatorService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $resourceCategory = strtoupper((string) ($filters['resourceCategory'] ?? 'ELEC'));
        $month = (string) ($filters['month'] ?? date('Y-m'));

        $kpiData = $this->kpiCalculatorService->computeSummary(
            resourceCategory: $resourceCategory,
            currentMonth: $month,
            countryCodes: $this->resolveCountryCodes($filters),
            resourceCategories: $this->resolveResourceCategories($filters),
            resourceSubCategory: $this->resolveResourceSubCategories($filters),
            onlyComparable: $this->resolveComparable($filters),
            realDataOnly: $this->resolveRealDataOnly($filters),
        );

        $resource = new KpiSummaryResource();
        $resource->resourceCategory = $resourceCategory;
        $resource->month = $month;
        $resource->energyIntensityMtd = $kpiData['energy_intensity_mtd'];
        $resource->energyIntensityYtd = $kpiData['energy_intensity_ytd'];
        $resource->evolutionMtdVsN1Percent = $kpiData['evolution_mtd_vs_n1_percent'];
        $resource->evolutionYtdVsN1Percent = $kpiData['evolution_ytd_vs_n1_percent'];
        $resource->totalConsumptionMtd = $kpiData['total_consumption_mtd'];
        $resource->totalConsumptionYtd = $kpiData['total_consumption_ytd'];
        $resource->refrigerantTotalYtdKg = $kpiData['refrigerant_total_ytd_kg'];
        $resource->salesSurfaceM2 = $kpiData['sales_surface_m2'];
        $resource->totalSurfaceM2 = $kpiData['total_surface_m2'];
        $resource->commercialEnergyIntensityYtd = $kpiData['commercial_energy_intensity_ytd'];
        $resource->buildingEnergyIntensityYtd = $kpiData['building_energy_intensity_ytd'];
        $resource->greenElectricityConsumptionKwh = $kpiData['green_electricity_consumption_kwh'];
        $resource->greenElectricityConsumptionPercent = $kpiData['green_electricity_consumption_percent'];
        $resource->greenElectricityProductionKwh = $kpiData['green_electricity_production_kwh'];
        $resource->greenElectricityProductionPercent = $kpiData['green_electricity_production_percent'];

        return [$resource];
    }
}
