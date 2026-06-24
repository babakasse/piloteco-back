<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiRefrigerantBreakdownResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiRefrigerantBreakdownResource>
 */
final readonly class KpiRefrigerantBreakdownProvider implements ProviderInterface
{
    use KpiFilterResolverTrait;

    public function __construct(
        private EnergyKpiCalculatorService $kpiCalculatorService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $month = (string) ($filters['month'] ?? date('Y-m'));
        $year = (int) substr($month, 0, 4);
        $monthFrom = sprintf('%d-01', $year);

        $rows = $this->kpiCalculatorService->computeRefrigerantBreakdown(
            monthFrom: $monthFrom,
            monthTo: $month,
            countryCodes: $this->resolveCountryCodes($filters),
            onlyComparable: $this->resolveComparable($filters),
        );

        return array_map(static function (array $row): KpiRefrigerantBreakdownResource {
            $resource = new KpiRefrigerantBreakdownResource();
            $resource->fluidType = $row['fluid_type'];
            $resource->totalKg = $row['total_kg'];
            $resource->percentage = $row['percentage'];
            return $resource;
        }, $rows);
    }
}
