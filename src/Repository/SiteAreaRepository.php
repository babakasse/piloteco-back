<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use App\Entity\SiteArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteArea>
 */
class SiteAreaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteArea::class);
    }

    public function findBySiteYearMonth(Site $site, int $fiscalYear, int $month): ?SiteArea
    {
        return $this->findOneBy([
            'site' => $site,
            'fiscalYear' => $fiscalYear,
            'month' => $month,
        ]);
    }

    /**
     * Get latest sales area for a site (most recent fiscal year, most recent month).
     */
    public function findLatestForSite(Site $site): ?SiteArea
    {
        return $this->createQueryBuilder('sa')
            ->where('sa.site = :site')
            ->andWhere('sa.salesAreaM2 IS NOT NULL')
            ->setParameter('site', $site)
            ->orderBy('sa.fiscalYear', 'DESC')
            ->addOrderBy('sa.month', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Average sales area per site for a given fiscal year (used for intensity KPI).
     *
     * @return array<array{site_unique_code: string, avg_sales_area: float}>
     */
    public function avgSalesAreaBySiteAndYear(int $fiscalYear, ?string $countryCode = null): array
    {
        $qb = $this->createQueryBuilder('sa')
            ->select('s.siteUniqueCode AS site_unique_code', 'AVG(sa.salesAreaM2) AS avg_sales_area')
            ->join('sa.site', 's')
            ->where('sa.fiscalYear = :year')
            ->andWhere('sa.salesAreaM2 IS NOT NULL')
            ->setParameter('year', $fiscalYear)
            ->groupBy('s.siteUniqueCode');

        if ($countryCode !== null) {
            $qb->andWhere('s.countryCode = :countryCode')
               ->setParameter('countryCode', $countryCode);
        }

        return $qb->getQuery()->getArrayResult();
    }
}
