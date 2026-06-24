<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiCountryIntensityMonthlyResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiCountryIntensityMonthlyResource>
 */
final readonly class KpiCountryIntensityMonthlyProvider implements ProviderInterface
{
    use KpiFilterResolverTrait;

    public function __construct(
        private EnergyKpiCalculatorService $kpiCalculatorService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $resourceCategory = strtoupper((string) ($filters['resourceCategory'] ?? 'ELEC'));
        $year = (int) ($filters['year'] ?? date('Y'));

        $rows = $this->kpiCalculatorService->computeCountryIntensityMonthly(
            resourceCategory: $resourceCategory,
            year: $year,
            countryCodes: $this->resolveCountryCodes($filters),
            resourceCategories: $this->resolveResourceCategories($filters),
            resourceSubCategory: $this->resolveResourceSubCategory($filters),
            onlyComparable: $this->resolveComparable($filters),
            realDataOnly: $this->resolveRealDataOnly($filters),
        );

        return array_map(static function (array $row): KpiCountryIntensityMonthlyResource {
            $resource = new KpiCountryIntensityMonthlyResource();
            $resource->id = $row['month'] . '_' . $row['country_code'];
            $resource->month = $row['month'];
            $resource->countryCode = $row['country_code'];
            $resource->intensity = $row['intensity'];
            $resource->totalKwh = $row['total_kwh'];
            return $resource;
        }, $rows);
    }
}
