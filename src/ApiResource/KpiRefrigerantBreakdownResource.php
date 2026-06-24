<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiRefrigerantBreakdownProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiRefrigerantBreakdown',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/refrigerant-breakdown',
            provider: KpiRefrigerantBreakdownProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiRefrigerantBreakdownResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $fluidType = '';

    #[Groups(['kpi:read'])]
    public float $totalKg = 0.0;

    #[Groups(['kpi:read'])]
    public float $percentage = 0.0;
}
