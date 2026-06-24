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
     * Apply resource category, sub-category, comparable and data-source filters.
     *
     * @param list<string>|null $resourceCategories  when set, overrides $resourceCategory with IN clause
     */
    private function applyEnergyFilters(
        QueryBuilder $qb,
        string $resourceCategory,
        ?array $resourceCategories = null,
        ?string $resourceSubCategory = null,
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
            $qb->andWhere('ec.resourceSubCategory = :subCategory')
               ->setParameter('subCategory', $resourceSubCategory);
        }

        if ($onlyComparable !== null) {
            $qb->andWhere('ec.isComparable = :comparable')
               ->setParameter('comparable', $onlyComparable);
        }

        if ($realDataOnly === true) {
            $qb->andWhere('ec.estimatedTotalSurfaceFlag = false');
        }
    }

    // ── Public methods ─────────────────────────────────────────────────────────

    /**
     * Sum total surface consumption by site, month range and resource category.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $resourceCategories  multi-resource override
     * @return array<array{site_unique_code: string, country_code: string, total: float}>
     */
    public function sumByMonthRangeAndResource(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        ?string $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select(
                's.siteUniqueCode AS site_unique_code',
                's.countryCode AS country_code',
                'SUM(ec.totalSurfaceQuantityConsumed) AS total',
            )
            ->join('ec.site', 's')
            ->andWhere('ec.monthYear >= :monthFrom')
            ->andWhere('ec.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('s.siteUniqueCode', 's.countryCode');

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);

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
    public function monthlyTotals(
        string $resourceCategory,
        string $yearStart,
        string $yearEnd,
        ?array $countryCodes = null,
        ?string $siteUniqueCode = null,
        ?array $resourceCategories = null,
        ?string $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select('ec.monthYear AS month_year', 'SUM(ec.totalSurfaceQuantityConsumed) AS total')
            ->join('ec.site', 's')
            ->andWhere('ec.monthYear >= :yearStart')
            ->andWhere('ec.monthYear <= :yearEnd')
            ->setParameter('yearStart', $yearStart)
            ->setParameter('yearEnd', $yearEnd)
            ->groupBy('ec.monthYear')
            ->orderBy('ec.monthYear', 'ASC');

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);

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
    public function sumByCountryAndMonthRange(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        ?string $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select('s.countryCode AS country_code', 'SUM(ec.totalSurfaceQuantityConsumed) AS total')
            ->join('ec.site', 's')
            ->andWhere('ec.monthYear >= :monthFrom')
            ->andWhere('ec.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('s.countryCode')
            ->orderBy('s.countryCode', 'ASC');

        $this->applyEnergyFilters($qb, $resourceCategory, $resourceCategories, $resourceSubCategory, $onlyComparable, $realDataOnly);

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
    public function monthlyTotalsByCountry(
        string $resourceCategory,
        string $yearStart,
        string $yearEnd,
        ?array $countryCodes = null,
        ?array $resourceCategories = null,
        ?string $resourceSubCategory = null,
        ?bool $onlyComparable = null,
        ?bool $realDataOnly = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select(
                's.countryCode AS country_code',
                'ec.monthYear AS month_year',
                'SUM(ec.totalSurfaceQuantityConsumed) AS total',
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
    ): float {
        if ($subCategories === []) {
            return 0.0;
        }

        $qb = $this->createQueryBuilder('ec')
            ->select('SUM(ec.totalSurfaceQuantityConsumed) AS total')
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
