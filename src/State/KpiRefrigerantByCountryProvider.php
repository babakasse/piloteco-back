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

        $month = (string) ($filters['month'] ?? date('Y-m'));
        $countryCodes = $this->resolveCountryCodes($filters);

        $rows = $this->kpiCalculatorService->computeRefrigerantByCountry($month, $countryCodes);

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
