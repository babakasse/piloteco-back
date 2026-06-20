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
    public function __construct(
        private EnergyKpiCalculatorService $kpiCalculatorService,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @return list<string>|null
     */
    private function resolveCountryCodes(array $filters): ?array
    {
        $raw = $filters['countryCodes'] ?? null;

        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        $codes = is_array($raw) ? array_values($raw) : [$raw];
        $codes = array_filter(array_map('strtoupper', $codes));

        return $codes !== [] ? array_values($codes) : null;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $resourceCategory = strtoupper((string) ($filters['resourceCategory'] ?? 'ELEC'));
        $year = (int) ($filters['year'] ?? date('Y'));
        $countryCodes = $this->resolveCountryCodes($filters);

        $monthlyData = $this->kpiCalculatorService->computeMonthlyEvolution(
            $resourceCategory,
            $year,
            $countryCodes,
        );

        return array_map(static function (array $row): KpiMonthlyEvolutionResource {
            $resource = new KpiMonthlyEvolutionResource();
            $resource->month = $row['month'];
            $resource->current = $row['current'];
            $resource->previous = $row['previous'];
            return $resource;
        }, $monthlyData);
    }
}
