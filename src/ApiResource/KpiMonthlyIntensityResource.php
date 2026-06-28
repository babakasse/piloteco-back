<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiMonthlyIntensityProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiMonthlyIntensity',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/monthly-intensity',
            provider: KpiMonthlyIntensityProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiMonthlyIntensityResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $month = '';

    #[Groups(['kpi:read'])]
    public ?float $current = null;

    #[Groups(['kpi:read'])]
    public ?float $previous = null;

    #[Groups(['kpi:read'])]
    public ?float $evolutionPercent = null;
}
