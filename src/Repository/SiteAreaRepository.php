<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use App\Entity\SiteArea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Total sales area aggregated by country using the latest available fiscal year ≤ requested year.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{country_code: string, total_sales_area: float}>
     */
    public function totalSalesAreaByCountryAndYear(
        int $fiscalYear,
        ?array $countryCodes = null,
        bool $onlyMag = false,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): array {
        $subDql = 'SELECT MAX(sa2.fiscalYear) FROM App\Entity\SiteArea sa2'
            . ' WHERE sa2.site = s AND sa2.fiscalYear <= :year AND sa2.salesAreaM2 IS NOT NULL';

        $qb = $this->createQueryBuilder('sa')
            ->select('s.countryCode AS country_code', 'SUM(sa.salesAreaM2) AS total_sales_area')
            ->join('sa.site', 's')
            ->where('sa.fiscalYear = (' . $subDql . ')')
            ->andWhere('sa.salesAreaM2 IS NOT NULL')
            ->setParameter('year', $fiscalYear)
            ->groupBy('s.countryCode');

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        if ($onlyMag) {
            $qb->andWhere("s.siteUniqueCode LIKE '%_MAG'");
        }

        $this->applySiteFilters($qb, $siteTypes, $siteFormats);

        return $qb->getQuery()->getArrayResult();
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
     * Average total area (building area) per site using the latest available fiscal year ≤ requested year.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{site_unique_code: string, avg_total_area: float}>
     */
    public function avgTotalAreaBySiteAndYear(
        int $fiscalYear,
        ?array $countryCodes = null,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
    ): array {
        $subDql = 'SELECT MAX(sa2.fiscalYear) FROM App\Entity\SiteArea sa2'
            . ' WHERE sa2.site = s AND sa2.fiscalYear <= :year AND sa2.totalAreaM2 IS NOT NULL';

        $qb = $this->createQueryBuilder('sa')
            ->select('s.siteUniqueCode AS site_unique_code', 'AVG(sa.totalAreaM2) AS avg_total_area')
            ->join('sa.site', 's')
            ->where('sa.fiscalYear = (' . $subDql . ')')
            ->andWhere('sa.totalAreaM2 IS NOT NULL')
            ->setParameter('year', $fiscalYear)
            ->groupBy('s.siteUniqueCode');

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        $this->applySiteFilters($qb, $siteTypes, $siteFormats);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Average sales area per site using the latest available fiscal year ≤ the requested year.
     *
     * @param list<string>|null $countryCodes
     * @param list<string>|null $siteTypes
     * @param list<string>|null $siteFormats
     * @return array<array{site_unique_code: string, avg_sales_area: float}>
     */
    public function avgSalesAreaBySiteAndYear(
        int $fiscalYear,
        ?array $countryCodes = null,
        bool $onlyMag = false,
        ?array $siteTypes = null,
        ?array $siteFormats = null,
        ?array $siteUniqueCodes = null,
    ): array {
        $subDql = 'SELECT MAX(sa2.fiscalYear) FROM App\Entity\SiteArea sa2'
            . ' WHERE sa2.site = s AND sa2.fiscalYear <= :year AND sa2.salesAreaM2 IS NOT NULL';

        $qb = $this->createQueryBuilder('sa')
            ->select('s.siteUniqueCode AS site_unique_code', 'AVG(sa.salesAreaM2) AS avg_sales_area')
            ->join('sa.site', 's')
            ->where('sa.fiscalYear = (' . $subDql . ')')
            ->andWhere('sa.salesAreaM2 IS NOT NULL')
            ->setParameter('year', $fiscalYear)
            ->groupBy('s.siteUniqueCode');

        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }

        if ($onlyMag) {
            $qb->andWhere("s.siteUniqueCode LIKE '%_MAG'");
        }

        if ($siteUniqueCodes !== null) {
            if ($siteUniqueCodes === []) {
                return [];
            }
            $qb->andWhere('s.siteUniqueCode IN (:siteUniqueCodes)')
               ->setParameter('siteUniqueCodes', $siteUniqueCodes);
        }

        $this->applySiteFilters($qb, $siteTypes, $siteFormats);

        return $qb->getQuery()->getArrayResult();
    }
}
