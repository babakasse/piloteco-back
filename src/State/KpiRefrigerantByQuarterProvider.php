<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiRefrigerantByQuarterResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiRefrigerantByQuarterResource>
 */
final readonly class KpiRefrigerantByQuarterProvider implements ProviderInterface
{
    use KpiFilterResolverTrait;

    public function __construct(
        private EnergyKpiCalculatorService $kpiCalculatorService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $month = (string) ($filters['month'] ?? date('Y-m'));

        $rows = $this->kpiCalculatorService->computeRefrigerantByCountryQuarterly(
            currentMonth: $month,
            countryCodes: $this->resolveCountryCodes($filters),
            onlyComparable: $this->resolveComparable($filters),
        );

        return array_map(static function (array $row): KpiRefrigerantByQuarterResource {
            $resource = new KpiRefrigerantByQuarterResource();
            $resource->id = $row['quarter'] . '_' . $row['country_code'];
            $resource->quarter = $row['quarter'];
            $resource->quarterStart = $row['quarter_start'];
            $resource->quarterEnd = $row['quarter_end'];
            $resource->countryCode = $row['country_code'];
            $resource->totalKg = $row['total_kg'];
            return $resource;
        }, $rows);
    }
}
