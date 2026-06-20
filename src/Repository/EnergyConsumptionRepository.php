<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EnergyConsumption;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /**
     * Sum total surface consumption by site, month range and resource category.
     *
     * @return array<array{site_unique_code: string, country_code: string, total: float}>
     */
    public function sumByMonthRangeAndResource(
        string $resourceCategory,
        string $monthFrom,
        string $monthTo,
        ?string $countryCode = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select(
                's.siteUniqueCode AS site_unique_code',
                's.countryCode AS country_code',
                'SUM(ec.totalSurfaceQuantityConsumed) AS total',
            )
            ->join('ec.site', 's')
            ->where('ec.resourceCategory = :resource')
            ->andWhere('ec.monthYear >= :monthFrom')
            ->andWhere('ec.monthYear <= :monthTo')
            ->setParameter('resource', $resourceCategory)
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('s.siteUniqueCode', 's.countryCode');

        if ($countryCode !== null) {
            $qb->andWhere('s.countryCode = :countryCode')
               ->setParameter('countryCode', $countryCode);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Monthly consumption N vs N-1 for charts.
     *
     * @return array<array{month_year: string, total: float}>
     */
    public function monthlyTotals(
        string $resourceCategory,
        string $yearStart,
        string $yearEnd,
        ?string $countryCode = null,
        ?string $siteUniqueCode = null,
    ): array {
        $qb = $this->createQueryBuilder('ec')
            ->select('ec.monthYear AS month_year', 'SUM(ec.totalSurfaceQuantityConsumed) AS total')
            ->join('ec.site', 's')
            ->where('ec.resourceCategory = :resource')
            ->andWhere('ec.monthYear >= :yearStart')
            ->andWhere('ec.monthYear <= :yearEnd')
            ->setParameter('resource', $resourceCategory)
            ->setParameter('yearStart', $yearStart)
            ->setParameter('yearEnd', $yearEnd)
            ->groupBy('ec.monthYear')
            ->orderBy('ec.monthYear', 'ASC');

        if ($countryCode !== null) {
            $qb->andWhere('s.countryCode = :countryCode')
               ->setParameter('countryCode', $countryCode);
        }

        if ($siteUniqueCode !== null) {
            $qb->andWhere('s.siteUniqueCode = :siteUniqueCode')
               ->setParameter('siteUniqueCode', $siteUniqueCode);
        }

        return $qb->getQuery()->getArrayResult();
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
