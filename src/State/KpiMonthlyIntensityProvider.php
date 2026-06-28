<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiMonthlyIntensityResource;
use App\Service\EnergyKpiCalculatorService;

final readonly class KpiMonthlyIntensityProvider implements ProviderInterface
{
    use KpiFilterResolverTrait;

    public function __construct(
        private EnergyKpiCalculatorService $kpiService,
    ) {}

    /**
     * Query params:
     *   - resourceCategory (string, default ELEC)
     *   - year (int)
     *   - month (string YYYY-MM)
     *   - surfaceType (sales|total, default sales)
     *   - mode (ytd|mtd, default ytd)
     *   - countryCodes, comparable, dataSource, resourceCategories, resourceSubCategory
     *   - siteTypes, siteFormats
     *
     * @return list<KpiMonthlyIntensityResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $resourceCategory = strtoupper((string) ($filters['resourceCategory'] ?? 'ELEC'));
        $month = (string) ($filters['month'] ?? date('Y-m'));
        $year = isset($filters['year']) ? (int) $filters['year'] : (int) substr($month, 0, 4);

        if ((int) substr($month, 0, 4) !== $year) {
            $month = sprintf('%d-12', $year);
        }

        $surfaceType = in_array($filters['surfaceType'] ?? '', ['total', 'sales'], true)
            ? $filters['surfaceType']
            : 'sales';
        $mode = in_array($filters['mode'] ?? '', ['ytd', 'mtd'], true)
            ? $filters['mode']
            : 'ytd';

        $rows = $this->kpiService->computeMonthlyIntensity(
            resourceCategory: $resourceCategory,
            year: $year,
            currentMonth: $month,
            surfaceType: $surfaceType,
            mode: $mode,
            countryCodes: $this->resolveCountryCodes($filters),
            resourceCategories: $this->resolveResourceCategories($filters),
            resourceSubCategory: $this->resolveResourceSubCategories($filters),
            onlyComparable: $this->resolveComparable($filters),
            realDataOnly: $this->resolveRealDataOnly($filters),
            siteTypes: $this->resolveSiteTypes($filters),
            siteFormats: $this->resolveSiteFormats($filters),
        );

        return array_map(static function (array $row): KpiMonthlyIntensityResource {
            $resource = new KpiMonthlyIntensityResource();
            $resource->month = $row['month'];
            $resource->current = $row['current'];
            $resource->previous = $row['previous'];
            $resource->evolutionPercent = $row['evolutionPercent'];
            return $resource;
        }, $rows);
    }
}
