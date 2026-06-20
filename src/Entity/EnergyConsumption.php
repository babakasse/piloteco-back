<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\EnergyConsumptionRepository;
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
    normalizationContext: ['groups' => ['energy:read']],
)]
#[ORM\Entity(repositoryClass: EnergyConsumptionRepository::class)]
#[ORM\Table(name: 'energy_consumption')]
#[ORM\Index(columns: ['month_year'], name: 'idx_energy_month_year')]
#[ORM\Index(columns: ['resource_category'], name: 'idx_energy_resource_category')]
#[ORM\Index(columns: ['site_id', 'month_year', 'resource_category'], name: 'idx_energy_site_month_resource')]
#[ORM\UniqueConstraint(
    name: 'uq_energy_site_month_resource_sub',
    columns: ['site_id', 'month_year', 'resource_category', 'resource_sub_category']
)]
class EnergyConsumption
{
    #[Groups(['energy:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Groups(['energy:read'])]
    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'energyConsumptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    /**
     * Format: YYYY-MM (e.g. "2024-01")
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/')]
    #[Groups(['energy:read'])]
    #[ORM\Column(length: 7)]
    private string $monthYear;

    /**
     * ELEC | GAS | WATER
     */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['ELEC', 'GAS', 'WATER'])]
    #[Groups(['energy:read'])]
    #[ORM\Column(length: 20)]
    private string $resourceCategory;

    #[Groups(['energy:read'])]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $resourceSubCategory = null;

    #[Groups(['energy:read'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $foodSurfaceUnit = null;

    #[Groups(['energy:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $foodSurfaceQuantityConsumed = null;

    #[Groups(['energy:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $foodSurfaceQuantityEstimated = null;

    #[Groups(['energy:read'])]
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estimatedFoodSurfaceFlag = false;

    #[Groups(['energy:read'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $totalSurfaceUnit = null;

    #[Groups(['energy:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalSurfaceQuantityConsumed = null;

    #[Groups(['energy:read'])]
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $totalSurfaceQuantityEstimated = null;

    #[Groups(['energy:read'])]
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $estimatedTotalSurfaceFlag = false;

    #[Groups(['energy:read'])]
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

    public function getResourceCategory(): string
    {
        return $this->resourceCategory;
    }

    public function setResourceCategory(string $resourceCategory): self
    {
        $this->resourceCategory = $resourceCategory;
        return $this;
    }

    public function getResourceSubCategory(): ?string
    {
        return $this->resourceSubCategory;
    }

    public function setResourceSubCategory(?string $resourceSubCategory): self
    {
        $this->resourceSubCategory = $resourceSubCategory;
        return $this;
    }

    public function getFoodSurfaceUnit(): ?string
    {
        return $this->foodSurfaceUnit;
    }

    public function setFoodSurfaceUnit(?string $foodSurfaceUnit): self
    {
        $this->foodSurfaceUnit = $foodSurfaceUnit;
        return $this;
    }

    public function getFoodSurfaceQuantityConsumed(): ?float
    {
        return $this->foodSurfaceQuantityConsumed;
    }

    public function setFoodSurfaceQuantityConsumed(?float $foodSurfaceQuantityConsumed): self
    {
        $this->foodSurfaceQuantityConsumed = $foodSurfaceQuantityConsumed;
        return $this;
    }

    public function getFoodSurfaceQuantityEstimated(): ?float
    {
        return $this->foodSurfaceQuantityEstimated;
    }

    public function setFoodSurfaceQuantityEstimated(?float $foodSurfaceQuantityEstimated): self
    {
        $this->foodSurfaceQuantityEstimated = $foodSurfaceQuantityEstimated;
        return $this;
    }

    public function isEstimatedFoodSurfaceFlag(): bool
    {
        return $this->estimatedFoodSurfaceFlag;
    }

    public function setEstimatedFoodSurfaceFlag(bool $estimatedFoodSurfaceFlag): self
    {
        $this->estimatedFoodSurfaceFlag = $estimatedFoodSurfaceFlag;
        return $this;
    }

    public function getTotalSurfaceUnit(): ?string
    {
        return $this->totalSurfaceUnit;
    }

    public function setTotalSurfaceUnit(?string $totalSurfaceUnit): self
    {
        $this->totalSurfaceUnit = $totalSurfaceUnit;
        return $this;
    }

    public function getTotalSurfaceQuantityConsumed(): ?float
    {
        return $this->totalSurfaceQuantityConsumed;
    }

    public function setTotalSurfaceQuantityConsumed(?float $totalSurfaceQuantityConsumed): self
    {
        $this->totalSurfaceQuantityConsumed = $totalSurfaceQuantityConsumed;
        return $this;
    }

    public function getTotalSurfaceQuantityEstimated(): ?float
    {
        return $this->totalSurfaceQuantityEstimated;
    }

    public function setTotalSurfaceQuantityEstimated(?float $totalSurfaceQuantityEstimated): self
    {
        $this->totalSurfaceQuantityEstimated = $totalSurfaceQuantityEstimated;
        return $this;
    }

    public function isEstimatedTotalSurfaceFlag(): bool
    {
        return $this->estimatedTotalSurfaceFlag;
    }

    public function setEstimatedTotalSurfaceFlag(bool $estimatedTotalSurfaceFlag): self
    {
        $this->estimatedTotalSurfaceFlag = $estimatedTotalSurfaceFlag;
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
