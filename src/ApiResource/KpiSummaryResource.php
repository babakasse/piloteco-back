<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiSummaryProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiSummary',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/summary',
            provider: KpiSummaryProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiSummaryResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $id = 'summary';

    #[Groups(['kpi:read'])]
    public ?float $energyIntensityMtd = null;

    #[Groups(['kpi:read'])]
    public ?float $energyIntensityYtd = null;

    #[Groups(['kpi:read'])]
    public ?float $evolutionMtdVsN1Percent = null;

    #[Groups(['kpi:read'])]
    public ?float $evolutionYtdVsN1Percent = null;

    #[Groups(['kpi:read'])]
    public ?float $totalConsumptionMtd = null;

    #[Groups(['kpi:read'])]
    public ?float $totalConsumptionYtd = null;

    #[Groups(['kpi:read'])]
    public ?float $refrigerantTotalYtdKg = null;

    #[Groups(['kpi:read'])]
    public string $resourceCategory = 'ELEC';

    #[Groups(['kpi:read'])]
    public string $month = '';
}
