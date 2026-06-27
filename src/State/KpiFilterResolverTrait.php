<?php

declare(strict_types=1);

namespace App\State;

/**
 * Shared query-param resolver helpers for all KPI providers.
 */
trait KpiFilterResolverTrait
{
    /**
     * @param array<string, mixed> $filters
     * @return list<string>|null  null = no filter (all countries)
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

    /**
     * @param array<string, mixed> $filters
     * @return list<string>|null  null = no filter (all resource categories)
     */
    private function resolveResourceCategories(array $filters): ?array
    {
        $raw = $filters['resourceCategories'] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        $cats = is_array($raw) ? array_values($raw) : [$raw];
        $cats = array_filter(array_map('strtoupper', $cats));
        return $cats !== [] ? array_values($cats) : null;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function resolveResourceSubCategory(array $filters): ?string
    {
        $raw = $filters['resourceSubCategory'] ?? null;
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }
        return trim((string) $raw);
    }

    /**
     * Returns true (only comparable), false (only non-comparable), or null (no filter).
     *
     * @param array<string, mixed> $filters
     */
    private function resolveComparable(array $filters): ?bool
    {
        return match($filters['comparable'] ?? null) {
            'comparable' => true,
            'non-comparable' => false,
            default => null,
        };
    }

    /**
     * Returns true (real data only) or null (all data including estimated).
     *
     * @param array<string, mixed> $filters
     */
    private function resolveRealDataOnly(array $filters): ?bool
    {
        return ($filters['dataSource'] ?? 'total') === 'real' ? true : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<string>|null
     */
    private function resolveSiteTypes(array $filters): ?array
    {
        $raw = $filters['siteTypes'] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        $types = is_array($raw) ? array_values($raw) : [$raw];
        $types = array_filter(array_map('strtoupper', $types));
        return $types !== [] ? array_values($types) : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<string>|null
     */
    private function resolveSiteFormats(array $filters): ?array
    {
        $raw = $filters['siteFormats'] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        $formats = is_array($raw) ? array_values($raw) : [$raw];
        $formats = array_filter($formats);
        return $formats !== [] ? array_values($formats) : null;
    }
}
