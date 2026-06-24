<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Site>
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    public function findBySiteUniqueCode(string $siteUniqueCode): ?Site
    {
        return $this->findOneBy(['siteUniqueCode' => $siteUniqueCode]);
    }

    /**
     * @return Site[]
     */
    public function findByCountryCode(string $countryCode): array
    {
        return $this->findBy(['countryCode' => $countryCode], ['siteUniqueCode' => 'ASC']);
    }

    /**
     * @return string[]
     */
    public function findAllCountryCodes(): array
    {
        return $this->createQueryBuilder('s')
            ->select('DISTINCT s.countryCode')
            ->orderBy('s.countryCode', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
