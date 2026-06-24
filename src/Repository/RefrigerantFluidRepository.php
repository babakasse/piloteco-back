<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefrigerantFluid;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefrigerantFluid>
 */
class RefrigerantFluidRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefrigerantFluid::class);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function applyComparableFilter(QueryBuilder $qb, ?bool $onlyComparable): void
    {
        if ($onlyComparable !== null) {
            $qb->andWhere('rf.isComparable = :comparable')
               ->setParameter('comparable', $onlyComparable);
        }
    }

    private function applyCountryFilter(QueryBuilder $qb, ?array $countryCodes): void
    {
        if ($countryCodes !== null && $countryCodes !== []) {
            $qb->andWhere('s.countryCode IN (:countryCodes)')
               ->setParameter('countryCodes', $countryCodes);
        }
    }

    // ── Public methods ─────────────────────────────────────────────────────────

    /**
     * Total refrigerant reloaded by month range (for YTD/MTD KPIs).
     *
     * @param list<string>|null $countryCodes
     * @return array<array{month_year: string, total_kg: float}>
     */
    public function sumByMonthRange(
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
    ): array {
        $qb = $this->createQueryBuilder('rf')
            ->select('rf.monthYear AS month_year', 'SUM(rf.quantityReloaded) AS total_kg')
            ->join('rf.site', 's')
            ->where('rf.monthYear >= :monthFrom')
            ->andWhere('rf.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('rf.monthYear')
            ->orderBy('rf.monthYear', 'ASC');

        $this->applyCountryFilter($qb, $countryCodes);
        $this->applyComparableFilter($qb, $onlyComparable);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Total refrigerant reloaded aggregated by country for a given month range.
     *
     * @param list<string>|null $countryCodes
     * @return array<array{country_code: string, total_kg: float}>
     */
    public function sumByCountryAndMonthRange(
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
    ): array {
        $qb = $this->createQueryBuilder('rf')
            ->select('s.countryCode AS country_code', 'SUM(rf.quantityReloaded) AS total_kg')
            ->join('rf.site', 's')
            ->where('rf.monthYear >= :monthFrom')
            ->andWhere('rf.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('s.countryCode')
            ->orderBy('s.countryCode', 'ASC');

        $this->applyCountryFilter($qb, $countryCodes);
        $this->applyComparableFilter($qb, $onlyComparable);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Monthly refrigerant reloaded per country (for quarterly YTD chart).
     *
     * @param list<string>|null $countryCodes
     * @return array<array{country_code: string, month_year: string, total_kg: float}>
     */
    public function monthlyByCountry(
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
    ): array {
        $qb = $this->createQueryBuilder('rf')
            ->select(
                's.countryCode AS country_code',
                'rf.monthYear AS month_year',
                'SUM(rf.quantityReloaded) AS total_kg',
            )
            ->join('rf.site', 's')
            ->where('rf.monthYear >= :monthFrom')
            ->andWhere('rf.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('s.countryCode', 'rf.monthYear')
            ->orderBy('rf.monthYear', 'ASC')
            ->addOrderBy('s.countryCode', 'ASC');

        $this->applyCountryFilter($qb, $countryCodes);
        $this->applyComparableFilter($qb, $onlyComparable);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Total refrigerant reloaded aggregated by fluid type (for breakdown pie chart).
     *
     * @param list<string>|null $countryCodes
     * @return array<array{fluid_type: string, total_kg: float}>
     */
    public function sumByFluidType(
        string $monthFrom,
        string $monthTo,
        ?array $countryCodes = null,
        ?bool $onlyComparable = null,
    ): array {
        $qb = $this->createQueryBuilder('rf')
            ->select('rf.refrigerantFluidType AS fluid_type', 'SUM(rf.quantityReloaded) AS total_kg')
            ->join('rf.site', 's')
            ->where('rf.monthYear >= :monthFrom')
            ->andWhere('rf.monthYear <= :monthTo')
            ->setParameter('monthFrom', $monthFrom)
            ->setParameter('monthTo', $monthTo)
            ->groupBy('rf.refrigerantFluidType')
            ->orderBy('total_kg', 'DESC');

        $this->applyCountryFilter($qb, $countryCodes);
        $this->applyComparableFilter($qb, $onlyComparable);

        return $qb->getQuery()->getArrayResult();
    }

    public function findBySiteMonthAndType(
        Site $site,
        string $monthYear,
        string $refrigerantFluidType,
    ): ?RefrigerantFluid {
        return $this->findOneBy([
            'site' => $site,
            'monthYear' => $monthYear,
            'refrigerantFluidType' => $refrigerantFluidType,
        ]);
    }
}
