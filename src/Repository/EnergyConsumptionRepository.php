<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EnergyConsumption;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnergyConsumption>
 */
class EnergyConsumptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnergyConsumption::class);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve which surface-consumption column to aggregate based on the data-source mode.
     *
     * - "Réel" (realDataOnly = true)  → totalSurfaceQuantityConsumed (0 on estimated rows)
     * - "Total" (default)             → totalSurfaceQuantityEstimated (real + estimated values)
     *
     * For real rows both columns are equal; only estimated rows differ
     * (consumed = 0, estimated = the imputed value).
     */
    private function surfaceColumn(?bool $realDataOnly): string
    {
        return $realDataOnly === true
            ? 'ec.totalSurfaceQuantityConsumed'
            : 'ec.totalSurfaceQuantityEstimated';
    }

    /**
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     */
    private function applySiteFilters(
        QueryBuilder $qb,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): void {
        if ($siteTypes !== null && $siteTypes !== []) {
            $qb->andWhere('s.siteType IN (:siteTypes)')
               ->setParameter('siteTypes', $siteTypes);
        }
        if ($siteFormats !== null && $siteFormats !== []) {
            $qb->andWhere('s.siteFormat IN (:siteFormats)')
               ->setParameter('siteFormats', $siteFormats);
        }
    }

    /**
     * Apply resource category, sub-category, comparable and data-source filters.
     *
     * @param list<string>|null $resourceCategories    when set, overrides $resourceCategory with IN clause
     * @param list<string>|string|null $resourceSubCategory  single value (legacy) or list of values
     */
    private function applyEnergyFilters(
        QueryBuilder $qb,
        string $resourceCategory,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): void {
        if ($resourceCategories !== null && $resourceCategories !== []) {
            $qb->andWhere('ec.resourceCategory IN (:resourceCategories)')
               ->setParameter('resourceCategories', $resourceCategories);
        } else {
            $qb->andWhere('ec.resourceCategory = :resource')
               ->setParameter('resource', $resourceCategory);
        }

        if ($resourceSubCategory !== null) {
            $subCats = is_array($resourceSubCategory) ? $resourceSubCategory : [$resourceSubCategory];
            if (count($subCats) === 1) {
                $qb->andWhere('ec.resourceSubCategory = :subCategory')
                   ->setParameter('subCategory', $subCats[0]);
            } else {
                $qb->andWhere('ec.resourceSubCategory IN (:subCategories)')
                   ->setParameter('subCategories', $subCats);
            }
        }

        if ($onlyComparable !== null) {
            $qb->andWhere('ec.isComparable = :comparable')
               ->setParameter('comparable', $onlyComparable);
        }

        // Note: the real-vs-total distinction is handled by switching the aggregated
        // column (see surfaceColumn()), not by filtering on the estimated flag.
    }

    // ── Public methods ─────────────────────────────────────────────────────────

    /**
     * Sum total surface consumption by site, month range and resource category.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories  multi-resource override
     * @return array<array{site_unique_code: string, country_code: string, total: float}>
     */
    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories  multi-resource override
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{site_unique_code: string, country_code: string, total: float}>
     */
    public function sumByMonthRangeAndResource(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select(
                's.siteUniqueCode AS site_unique_code',
                's.countryCode AS country_code',
                'SUM(' . $this->surfaceColumn($realDataOnly) . ') AS total',
            )
            ->join('ec.site', 's')
            ->andWhere('ec.monthYear >= :monthFrom')
            ->andWhere('ec.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('s.siteUniqueCode', 's.countryCode');

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);
        $this->applySiteFilters($qb, $siteTypes, $siteFormats);

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Monthly consumption N vs N-1 for charts.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return array<array{month_year: string, total: float}>
     */
    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{month_year: string, total: float}>
     */
    public function monthlyTotals(
        string $resourceCategory,
        string $yearStart,
        string $yearEnd,
        ?array $countryCodes = null,
        ?string $siteUniqueCode = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select('ec.monthYear AS month_year', 'SUM(' . $this->surfaceColumn($realDataOnly) . ') AS total')
            ->join('ec.site', 's')
            ->andWhere('ec.monthYear >= :yearStart')
            ->andWhere('ec.monthYear <= :yearEnd')
            ->setParameter('yearStart', $yearStart)
            ->setParameter('yearEnd', $yearEnd)
            ->groupBy('ec.monthYear')
            ->orderBy('ec.monthYear', 'ASC');

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);
        $this->applySiteFilters($qb, $siteTypes, $siteFormats);

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        if ($siteUniqueCode !== null) {
            $qb->andWhere('s.siteUniqueCode = :siteUniqueCode')
               ->setParameter('siteUniqueCode', $siteUniqueCode);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Sum total consumption aggregated by country, month range and resource category.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return array<array{country_code: string, total: float}>
     */
    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{country_code: string, total: float}>
     */
    public function sumByCountryAndMonthRange(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select('s.countryCode AS country_code', 'SUM(' . $this->surfaceColumn($realDataOnly) . ') AS total')
            ->join('ec.site', 's')
            ->andWhere('ec.monthYear >= :monthFrom')
            ->andWhere('ec.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('s.countryCode')
            ->orderBy('s.countryCode', 'ASC');

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);
        $this->applySiteFilters($qb, $siteTypes, $siteFormats);

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Monthly consumption per country for YTD country intensity chart.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return array<array{country_code: string, month_year: string, total: float}>
     */
    /**
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{country_code: string, month_year: string, total: float}>
     */
    public function monthlyTotalsByCountry(
        string $resourceCategory,
        string $yearStart,
        string $yearEnd,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select(
                's.countryCode AS country_code',
                'ec.monthYear AS month_year',
                'SUM(' . $this->surfaceColumn($realDataOnly) . ') AS total',
            )
            ->join('ec.site', 's')
            ->andWhere('ec.monthYear >= :yearStart')
            ->andWhere('ec.monthYear <= :yearEnd')
            ->setParameter('yearStart', $yearStart)
            ->setParameter('yearEnd', $yearEnd)
            ->groupBy('s.countryCode', 'ec.monthYear')
            ->orderBy('ec.monthYear', 'ASC')
            ->addOrderBy('s.countryCode', 'ASC');

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);
        $this->applySiteFilters($qb, $siteTypes, $siteFormats);

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Sum consumption for ELEC filtered by a list of sub-categories (IN clause).
     * Used to compute green electricity metrics (consumption and production).
     *
     * @param list<string>      $subCategories  sub-categories to include
     * @param list<string>|null $countryCodes
     */
    public function sumByMonthRangeAndSubCategories(
        string $monthFrom,
        string $monthTo,
        array $subCategories,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): float {
        if ($subCategories === []) {
            return 0.0;
        }

        $qb = $this->createQueryBuilder('ec')
            ->select('SUM(' . $this->surfaceColumn($realDataOnly) . ') AS total')
            ->join('ec.site', 's')
            ->andWhere('ec.resourceCategory = :resource')
            ->andWhere('ec.monthYear >= :monthFrom')
            ->andWhere('ec.monthYear <= :monthTo')
            ->andWhere('ec.resourceSubCategory IN (:subCategories)')
            ->setParameter('resource', 'ELEC')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->setParameter('subCategories', $subCategories);

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        if ($onlyComparable !== null) {
            $qb->andWhere('ec.isComparable = :comparable')
               ->setParameter('comparable', $onlyComparable);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    /**
     * Returns unique site codes of MAG sites that have at least one non-null
     * consumption record for the given resource and month range.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories
     * @return list<string>
     */
    public function findMagSiteCodesWithConsumption(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        array|string|null $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $surfaceColumn = $this->surfaceColumn($realDataOnly);
        $qb = $this->createQueryBuilder('ec')
            ->select('DISTINCT s.siteUniqueCode')
            ->join('ec.site', 's')
            ->andWhere("s.siteUniqueCode LIKE '%_MAG'")
            ->andWhere('ec.monthYear >= :monthFrom')
            ->andWhere('ec.monthYear <= :monthTo')
            ->andWhere($surfaceColumn . ' IS NOT NULL')
            ->andWhere($surfaceColumn . ' > 0')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo);

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        return array_column($qb->getQuery()->getArrayResult(), 'siteUniqueCode');
    }

    public function findBySiteMonthAndResource(
        Site $site,
        string $monthYear,
        string $resourceCategory,
        ?string $resourceSubCategory,
    ): ?EnergyConsumption {
        return $this->findOneBy([
            'site' => $site,
            'monthYear' => $monthYear,
            'resourceCategory' => $resourceCategory,
            'resourceSubCategory' => $resourceSubCategory,
        ]);
    }
}
