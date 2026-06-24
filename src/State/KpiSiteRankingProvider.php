<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiSiteRankingResource;
use App\Service\EnergyKpiCalculatorService;

/**
 * @implements ProviderInterface<KpiSiteRankingResource>
 */
final readonly class KpiSiteRankingProvider implements ProviderInterface
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
        $order = strtoupper((string) ($filters['order'] ?? 'DESC'));
        $limit = min((int) ($filters['limit'] ?? 10), 50);

        $year = (int) substr($month, 0, 4);
        $monthFrom = sprintf('%d-01', $year);

        $rankings = $this->kpiCalculatorService->computeSiteRanking(
            resourceCategory: $resourceCategory,
            monthFrom: $monthFrom,
            monthTo: $month,
            limit: $limit,
            order: $order,
            countryCodes: $this->resolveCountryCodes($filters),
            resourceCategories: $this->resolveResourceCategories($filters),
            resourceSubCategory: $this->resolveResourceSubCategory($filters),
            onlyComparable: $this->resolveComparable($filters),
            realDataOnly: $this->resolveRealDataOnly($filters),
        );

        return array_map(static function (array $row): KpiSiteRankingResource {
            $resource = new KpiSiteRankingResource();
            $resource->rank = $row['rank'];
            $resource->siteUniqueCode = $row['site_unique_code'];
            $resource->countryCode = $row['country_code'];
            $resource->intensity = $row['intensity'];
            $resource->evolutionPercent = $row['evolution_percent'];
            return $resource;
        }, $rankings);
    }
}
