<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefrigerantFluid;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /**
     * Total refrigerant reloaded by month range (for YTD/MTD KPIs).
     *
     * @return array<array{month_year: string, total_kg: float}>
     */
    public function sumByMonthRange(
        string $monthFrom,
        string $monthTo,
        ?string $countryCode = null,
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

        if ($countryCode !== null) {
            $qb->andWhere('s.countryCode = :countryCode')
               ->setParameter('countryCode', $countryCode);
        }

        return $qb->getQuery()->getArrayResult();
    }
}
