<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SiteAreaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: true,
            paginationItemsPerPage: 100,
            paginationMaximumItemsPerPage: 500,
            paginationClientItemsPerPage: true,
        ),
        new Get(),
    ],
    normalizationContext: ['groups' => ['area:read']],
)]
#[ORM\Entity(repositoryClass: SiteAreaRepository::class)]
#[ORM\Table(name: 'site_area')]
#[ORM\Index(columns: ['fiscal_year'], name: 'idx_area_fiscal_year')]
#[ORM\Index(columns: ['site_id', 'fiscal_year', 'month'], name: 'idx_area_site_year_month')]
#[ORM\UniqueConstraint(name: 'uq_area_site_year_month', columns: ['site_id', 'fiscal_year', 'month'])]
class SiteArea
{
    #[Groups(['area:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Groups(['area:read'])]
    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'siteAreas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    /**
     * Fiscal year (e.g. 2024)
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 2000, max: 2100)]
    #[Groups(['area:read'])]
    #[ORM\Column(type: 'smallint')]
    private int $fiscalYear;

    /**
     * Month number 1-12
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 12)]
    #[Groups(['area:read'])]
    #[ORM\Column(type: 'smallint')]
    private int $month;

    /**
     * Sales surface in m² (surface de vente)
     */
    #[Groups(['area:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $salesAreaM2 = null;

    /**
     * Total surface in m² (surface totale)
     */
    #[Groups(['area:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalAreaM2 = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): self
    {
        $this->site = $site;
        return $this;
    }

    public function getFiscalYear(): int
    {
        return $this->fiscalYear;
    }

    public function setFiscalYear(int $fiscalYear): self
    {
        $this->fiscalYear = $fiscalYear;
        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;
        return $this;
    }

    public function getSalesAreaM2(): ?float
    {
        return $this->salesAreaM2;
    }

    public function setSalesAreaM2(?float $salesAreaM2): self
    {
        $this->salesAreaM2 = $salesAreaM2;
        return $this;
    }

    public function getTotalAreaM2(): ?float
    {
        return $this->totalAreaM2;
    }

    public function setTotalAreaM2(?float $totalAreaM2): self
    {
        $this->totalAreaM2 = $totalAreaM2;
        return $this;
    }
}
