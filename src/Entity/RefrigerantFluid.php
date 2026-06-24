<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\RefrigerantFluidRepository;
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
    normalizationContext: ['groups' => ['refrigerant:read']],
)]
#[ORM\Entity(repositoryClass: RefrigerantFluidRepository::class)]
#[ORM\Table(name: 'refrigerant_fluid')]
#[ORM\Index(columns: ['month_year'], name: 'idx_refrigerant_month_year')]
#[ORM\Index(columns: ['refrigerant_fluid_type'], name: 'idx_refrigerant_type')]
#[ORM\Index(columns: ['site_id', 'month_year'], name: 'idx_refrigerant_site_month')]
#[ORM\UniqueConstraint(
    name: 'uq_refrigerant_site_month_type',
    columns: ['site_id', 'month_year', 'refrigerant_fluid_type']
)]
class RefrigerantFluid
{
    #[Groups(['refrigerant:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Groups(['refrigerant:read'])]
    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'refrigerantFluids')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    /**
     * Format: YYYY-MM (e.g. "2024-01")
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/')]
    #[Groups(['refrigerant:read'])]
    #[ORM\Column(length: 7)]
    private string $monthYear;

    /**
     * Type of refrigerant fluid (e.g. R404A, R134a, R410A…)
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['refrigerant:read'])]
    #[ORM\Column(length: 50)]
    private string $refrigerantFluidType;

    #[Groups(['refrigerant:read'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unitOfMeasure = null;

    #[Groups(['refrigerant:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $quantityReloaded = null;

    #[Groups(['refrigerant:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $quantityEstimated = null;

    #[Groups(['refrigerant:read'])]
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estimatedValueFlag = false;

    #[Groups(['refrigerant:read'])]
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isComparable = true;

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

    public function getMonthYear(): string
    {
        return $this->monthYear;
    }

    public function setMonthYear(string $monthYear): self
    {
        $this->monthYear = $monthYear;
        return $this;
    }

    public function getRefrigerantFluidType(): string
    {
        return $this->refrigerantFluidType;
    }

    public function setRefrigerantFluidType(string $refrigerantFluidType): self
    {
        $this->refrigerantFluidType = $refrigerantFluidType;
        return $this;
    }

    public function getUnitOfMeasure(): ?string
    {
        return $this->unitOfMeasure;
    }

    public function setUnitOfMeasure(?string $unitOfMeasure): self
    {
        $this->unitOfMeasure = $unitOfMeasure;
        return $this;
    }

    public function getQuantityReloaded(): ?float
    {
        return $this->quantityReloaded;
    }

    public function setQuantityReloaded(?float $quantityReloaded): self
    {
        $this->quantityReloaded = $quantityReloaded;
        return $this;
    }

    public function getQuantityEstimated(): ?float
    {
        return $this->quantityEstimated;
    }

    public function setQuantityEstimated(?float $quantityEstimated): self
    {
        $this->quantityEstimated = $quantityEstimated;
        return $this;
    }

    public function isEstimatedValueFlag(): bool
    {
        return $this->estimatedValueFlag;
    }

    public function setEstimatedValueFlag(bool $estimatedValueFlag): self
    {
        $this->estimatedValueFlag = $estimatedValueFlag;
        return $this;
    }

    public function isComparable(): bool
    {
        return $this->isComparable;
    }

    public function setIsComparable(bool $isComparable): self
    {
        $this->isComparable = $isComparable;
        return $this;
    }
}
