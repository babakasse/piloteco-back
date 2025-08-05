<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Controller\AssessmentController;
use App\Repository\CarbonAssessmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/assessment',
            controller: AssessmentController::class . '::getAssessments',
            name: 'app_get_assessments'
        ),
        new Get(
            uriTemplate: '/assessment/{id}',
            controller: AssessmentController::class . '::getAssessment',
            name: 'app_get_assessment'
        ),
        new Post(
            uriTemplate: '/assessment',
            controller: AssessmentController::class . '::createAssessment',
            name: 'app_create_assessment'
        ),
        new Get(
            uriTemplate: '/assessment/{id}/emissions',
            controller: AssessmentController::class . '::getAssessmentEmissions',
            name: 'app_get_assessment_emissions'
        ),
        new Get(
            uriTemplate: '/assessment/{id}/summary',
            controller: AssessmentController::class . '::getAssessmentSummary',
            name: 'app_get_assessment_summary'
        )
    ],
    normalizationContext: ['groups' => ['carbon_assessment:read']],
    denormalizationContext: ['groups' => ['carbon_assessment:create', 'carbon_assessment:update']],
)]
#[ORM\Entity(repositoryClass: CarbonAssessmentRepository::class)]
#[ORM\Table(name: '`carbon_assessment`')]
#[ORM\HasLifecycleCallbacks]
class CarbonAssessment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['carbon_assessment:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups(['carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?string $description = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull]
    #[Groups(['carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?\DateTimeInterface $assessmentDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['carbon_assessment:read'])]
    private ?int $year = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['carbon_assessment:read'])]
    private ?float $totalEmissions = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['carbon_assessment:read'])]
    private ?float $scope1Emissions = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['carbon_assessment:read'])]
    private ?float $scope2Emissions = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['carbon_assessment:read'])]
    private ?float $scope3Emissions = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['carbon_assessment:read', 'carbon_assessment:create'])]
    private ?Company $company = null;

    #[ORM\OneToMany(mappedBy: 'assessment', targetEntity: Emission::class, cascade: ['persist', 'remove'])]
    #[Groups(['carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private Collection $emissions;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['carbon_assessment:read', 'carbon_assessment:create', 'carbon_assessment:update'])]
    private ?string $status = 'draft';

    #[ORM\Column(type: 'datetime')]
    #[Groups(['carbon_assessment:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['carbon_assessment:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->emissions = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->assessmentDate = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getAssessmentDate(): ?\DateTimeInterface
    {
        return $this->assessmentDate;
    }

    public function setAssessmentDate(\DateTimeInterface $assessmentDate): self
    {
        $this->assessmentDate = $assessmentDate;
        $this->year = (int) $assessmentDate->format('Y');
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getTotalEmissions(): ?float
    {
        $this->updateCalculations();
        return $this->totalEmissions;
    }

    public function setTotalEmissions(?float $totalEmissions): self
    {
        $this->totalEmissions = $totalEmissions;
        return $this;
    }

    public function getScope1Emissions(): ?float
    {
        $this->updateCalculations();
        return $this->scope1Emissions;
    }

    public function setScope1Emissions(?float $scope1Emissions): self
    {
        $this->scope1Emissions = $scope1Emissions;
        return $this;
    }

    public function getScope2Emissions(): ?float
    {
        $this->updateCalculations();
        return $this->scope2Emissions;
    }

    public function setScope2Emissions(?float $scope2Emissions): self
    {
        $this->scope2Emissions = $scope2Emissions;
        return $this;
    }

    public function getScope3Emissions(): ?float
    {
        $this->updateCalculations();
        return $this->scope3Emissions;
    }

    public function setScope3Emissions(?float $scope3Emissions): self
    {
        $this->scope3Emissions = $scope3Emissions;
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    /**
     * @return Collection<int, Emission>
     */
    public function getEmissions(): Collection
    {
        return $this->emissions;
    }

    public function addEmission(Emission $emission): self
    {
        if (!$this->emissions->contains($emission)) {
            $this->emissions[] = $emission;
            $emission->setAssessment($this);
        }

        return $this;
    }

    public function removeEmission(Emission $emission): self
    {
        if ($this->emissions->removeElement($emission)) {
            // set the owning side to null (unless already changed)
            if ($emission->getAssessment() === $this) {
                $emission->setAssessment(null);
            }
        }

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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

    /**
     * Calcule les émissions totales et par scope en tenant compte des unités (kgCO2e -> tCO2e).
     * Arrondit à 2 chiffres après la virgule.
     */
    public function calculateEmissions(): void
    {
        $scope1 = 0;
        $scope2 = 0;
        $scope3 = 0;

        foreach ($this->emissions as $emission) {
            $amount = $emission->getAmount() ?? 0;
            $unit = $emission->getUnit();
            if ($unit === 'kgCO2e') {
                $amount = $amount / 1000;
            }
            switch ($emission->getScope()) {
                case 1:
                    $scope1 += $amount;
                    break;
                case 2:
                    $scope2 += $amount;
                    break;
                case 3:
                    $scope3 += $amount;
                    break;
            }
        }

        $this->scope1Emissions = round($scope1, 2);
        $this->scope2Emissions = round($scope2, 2);
        $this->scope3Emissions = round($scope3, 2);
        $this->totalEmissions = round($scope1 + $scope2 + $scope3, 2);
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateCalculations(): void
    {
        $this->calculateEmissions();
    }
}
