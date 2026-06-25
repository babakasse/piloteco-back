<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiMonthlyEvolutionResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiMonthlyEvolutionResource>
 */
final readonly class KpiMonthlyEvolutionProvider implements ProviderInterface
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
        $month = (string) ($filters['month'] ?? date('Y-m'));

        $monthlyData = $this->kpiCalculatorService->computeMonthlyEvolution(
            resourceCategory: $resourceCategory,
            year: $year,
            currentMonth: $month,
            countryCodes: $this->resolveCountryCodes($filters),
            resourceCategories: $this->resolveResourceCategories($filters),
            resourceSubCategory: $this->resolveResourceSubCategory($filters),
            onlyComparable: $this->resolveComparable($filters),
            realDataOnly: $this->resolveRealDataOnly($filters),
        );

        return array_map(static function (array $row): KpiMonthlyEvolutionResource {
            $resource = new KpiMonthlyEvolutionResource();
            $resource->month = $row['month'];
            $resource->current = $row['current'];
            $resource->previous = $row['previous'];
            $resource->evolutionPercent = $row['evolutionPercent'];
            return $resource;
        }, $monthlyData);
    }
}
