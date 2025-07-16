<?php

namespace App\Repository;

use App\Entity\Emission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Emission>
 *
 * @method Emission|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emission|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emission[]    findAll()
 * @method Emission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Emission::class);
    }

    /**
     * Find emissions for a specific assessment
     */
    public function findByAssessment(int $assessmentId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.assessment = :assessmentId')
            ->setParameter('assessmentId', $assessmentId)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find emissions for a specific scope
     */
    public function findByScope(int $scope): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.scope = :scope')
            ->setParameter('scope', $scope)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find emissions for a specific assessment and scope
     */
    public function findByAssessmentAndScope(int $assessmentId, int $scope): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.assessment = :assessmentId')
            ->andWhere('e.scope = :scope')
            ->setParameter('assessmentId', $assessmentId)
            ->setParameter('scope', $scope)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find emissions by category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.category = :category')
            ->setParameter('category', $category)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate total emissions for an assessment
     */
    public function calculateTotalForAssessment(int $assessmentId): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount) as total')
            ->andWhere('e.assessment = :assessmentId')
            ->setParameter('assessmentId', $assessmentId)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (float) ($result ?? 0);
    }

    /**
     * Calculate emissions by scope for an assessment
     */
    public function calculateByScope(int $assessmentId, int $scope): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount) as total')
            ->andWhere('e.assessment = :assessmentId')
            ->andWhere('e.scope = :scope')
            ->setParameter('assessmentId', $assessmentId)
            ->setParameter('scope', $scope)
            ->getQuery()
            ->getSingleScalarResult();
        
        return (float) ($result ?? 0);
    }
}