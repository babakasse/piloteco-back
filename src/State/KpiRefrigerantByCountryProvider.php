<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiRefrigerantByCountryResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiRefrigerantByCountryResource>
 */
final readonly class KpiRefrigerantByCountryProvider implements ProviderInterface
{
    use KpiFilterResolverTrait;

    public function __construct(
        private EnergyKpiCalculatorService $kpiCalculatorService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $month = (string) ($filters['month'] ?? date('Y-m'));

        $rows = $this->kpiCalculatorService->computeRefrigerantByCountry(
            currentMonth: $month,
            countryCodes: $this->resolveCountryCodes($filters),
            onlyComparable: $this->resolveComparable($filters),
        );

        return array_map(static function (array $row): KpiRefrigerantByCountryResource {
            $resource = new KpiRefrigerantByCountryResource();
            $resource->countryCode = $row['country_code'];
            $resource->totalKg = $row['total_kg'];
            $resource->quarterStart = $row['quarter_start'];
            $resource->quarterEnd = $row['quarter_end'];
            return $resource;
        }, $rows);
    }
}
