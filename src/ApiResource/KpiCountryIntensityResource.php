<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiCountryIntensityProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiCountryIntensity',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/country-intensity',
            provider: KpiCountryIntensityProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiCountryIntensityResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $countryCode = '';

    #[Groups(['kpi:read'])]
    public ?float $intensity = null;

    #[Groups(['kpi:read'])]
    public ?float $totalConsumptionKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $totalAreaM2 = null;
}
