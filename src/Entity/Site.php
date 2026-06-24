<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 200,
            paginationClientItemsPerPage: true,
        ),
        new Get(),
    ],
    normalizationContext: ['groups' => ['site:read']],
)]
#[ORM\Entity(repositoryClass: SiteRepository::class)]
#[ORM\Table(name: 'site')]
#[ORM\Index(columns: ['site_unique_code'], name: 'idx_site_unique_code')]
#[ORM\Index(columns: ['country_code'], name: 'idx_site_country_code')]
#[ORM\UniqueConstraint(name: 'uq_site_unique_code', columns: ['site_unique_code'])]
class Site
{
    #[Groups(['site:read', 'energy:read', 'area:read', 'refrigerant:read'])]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['site:read', 'energy:read', 'area:read', 'refrigerant:read'])]
    #[ORM\Column(length: 50, unique: true)]
    private string $siteUniqueCode;

    #[Assert\NotBlank]
    #[Assert\Length(max: 5)]
    #[Groups(['site:read', 'energy:read', 'area:read', 'refrigerant:read'])]
    #[ORM\Column(length: 5)]
    private string $countryCode;

    #[Groups(['site:read'])]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $label = null;

    #[Groups(['site:read'])]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $siteType = null;

    #[ORM\OneToMany(mappedBy: 'site', targetEntity: EnergyConsumption::class, cascade: ['remove'])]
    private Collection $energyConsumptions;

    #[ORM\OneToMany(mappedBy: 'site', targetEntity: SiteArea::class, cascade: ['remove'])]
    private Collection $siteAreas;

    #[ORM\OneToMany(mappedBy: 'site', targetEntity: RefrigerantFluid::class, cascade: ['remove'])]
    private Collection $refrigerantFluids;

    public function __construct()
    {
        $this->energyConsumptions = new ArrayCollection();
        $this->siteAreas = new ArrayCollection();
        $this->refrigerantFluids = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteUniqueCode(): string
    {
        return $this->siteUniqueCode;
    }

    public function setSiteUniqueCode(string $siteUniqueCode): self
    {
        $this->siteUniqueCode = $siteUniqueCode;
        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getSiteType(): ?string
    {
        return $this->siteType;
    }

    public function setSiteType(?string $siteType): self
    {
        $this->siteType = $siteType;
        return $this;
    }

    public function getEnergyConsumptions(): Collection
    {
        return $this->energyConsumptions;
    }

    public function getSiteAreas(): Collection
    {
        return $this->siteAreas;
    }

    public function getRefrigerantFluids(): Collection
    {
        return $this->refrigerantFluids;
    }
}
