<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\EmissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            paginationMaximumItemsPerPage: 50,
            paginationClientItemsPerPage: true
        ),
        new Post(),
        new Get(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['emission:read']],
    denormalizationContext: ['groups' => ['emission:create', 'emission:update']],
)]
#[ORM\Entity(repositoryClass: EmissionRepository::class)]
#[ORM\Table(name: '`emission`')]
#[ORM\HasLifecycleCallbacks]
class Emission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['emission:read', 'carbon_assessment:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups(['emission:read', 'emission:create', 'emission:update', 'carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?string $source = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['emission:read', 'emission:create', 'emission:update', 'carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Groups(['emission:read', 'emission:create', 'emission:update', 'carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?string $category = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['emission:read', 'emission:create', 'emission:update', 'carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?float $amount = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Groups(['emission:read', 'emission:create', 'emission:update', 'carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?string $unit = 'tCO2e';

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull]
    #[Assert\Choice([1, 2, 3])]
    #[Groups(['emission:read', 'emission:create', 'emission:update', 'carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?int $scope = null;

    #[ORM\ManyToOne(targetEntity: CarbonAssessment::class, inversedBy: 'emissions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['emission:read', 'emission:create'])]
    private ?CarbonAssessment $assessment = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['emission:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['emission:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getScope(): ?int
    {
        return $this->scope;
    }

    public function setScope(int $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function getAssessment(): ?CarbonAssessment
    {
        return $this->assessment;
    }

    public function setAssessment(?CarbonAssessment $assessment): self
    {
        $this->assessment = $assessment;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PostPersist]
    #[ORM\PostUpdate]
    #[ORM\PostRemove]
    public function updateAssessmentCalculations(): void
    {
        if ($this->assessment) {
            $this->assessment->calculateEmissions();
        }
    }
}