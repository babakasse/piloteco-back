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
    public function __construct(
        private EnergyKpiCalculatorService $kpiCalculatorService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $resourceCategory = strtoupper((string) ($filters['resourceCategory'] ?? 'ELEC'));
        $month = (string) ($filters['month'] ?? date('Y-m'));
        $countryCode = ($filters['countryCode'] ?? null) ?: null;

        $kpiData = $this->kpiCalculatorService->computeSummary($resourceCategory, $month, $countryCode);

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

        return [$resource];
    }
}
