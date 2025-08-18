<?php

namespace App\Repository;

use App\Entity\CarbonAssessment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CarbonAssessment>
 *
 * @method CarbonAssessment|null find($id, $lockMode = null, $lockVersion = null)
 * @method CarbonAssessment|null findOneBy(array $criteria, array $orderBy = null)
 * @method CarbonAssessment[]    findAll()
 * @method CarbonAssessment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CarbonAssessmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarbonAssessment::class);
    }

    /**
     * Find assessments for a specific company
     */
    public function findByCompany(int $companyId): array
    {
        return $this->createQueryBuilder('ca')
            ->andWhere('ca.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('ca.assessmentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find assessments for a specific year
     */
    public function findByYear(int $year): array
    {
        return $this->createQueryBuilder('ca')
            ->andWhere('ca.year = :year')
            ->setParameter('year', $year)
            ->orderBy('ca.assessmentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find assessments for a specific company and year
     */
    public function findByCompanyAndYear(int $companyId, int $year): array
    {
        return $this->createQueryBuilder('ca')
            ->andWhere('ca.company = :companyId')
            ->andWhere('ca.year = :year')
            ->setParameter('companyId', $companyId)
            ->setParameter('year', $year)
            ->orderBy('ca.assessmentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the latest assessment for a company
     */
    public function findLatestForCompany(int $companyId): ?CarbonAssessment
    {
        return $this->createQueryBuilder('ca')
            ->andWhere('ca.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('ca.assessmentDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}