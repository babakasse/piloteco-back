<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiCountryIntensityResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiCountryIntensityResource>
 */
final readonly class KpiCountryIntensityProvider implements ProviderInterface
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

        $rows = $this->kpiCalculatorService->computeCountryIntensity(
            resourceCategory: $resourceCategory,
            currentMonth: $month,
            countryCodes: $this->resolveCountryCodes($filters),
            resourceCategories: $this->resolveResourceCategories($filters),
            resourceSubCategory: $this->resolveResourceSubCategories($filters),
            onlyComparable: $this->resolveComparable($filters),
            realDataOnly: $this->resolveRealDataOnly($filters),
        );

        return array_map(static function (array $row): KpiCountryIntensityResource {
            $resource = new KpiCountryIntensityResource();
            $resource->countryCode = $row['country_code'];
            $resource->intensity = $row['intensity'];
            $resource->totalConsumptionKwh = $row['total_consumption_kwh'];
            $resource->totalAreaM2 = $row['total_area_m2'];
            return $resource;
        }, $rows);
    }
}
