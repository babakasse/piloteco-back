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
        $month = (string) ($filters['month'] ?? date('Y-m'));
        $countryCodes = $this->resolveCountryCodes($filters);

        $rows = $this->kpiCalculatorService->computeCountryIntensity($resourceCategory, $month, $countryCodes);

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
