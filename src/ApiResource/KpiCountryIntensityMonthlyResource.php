<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiCountryIntensityMonthlyProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiCountryIntensityMonthly',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/country-intensity-monthly',
            provider: KpiCountryIntensityMonthlyProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiCountryIntensityMonthlyResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $id = '';

    #[Groups(['kpi:read'])]
    public string $month = '';

    #[Groups(['kpi:read'])]
    public string $countryCode = '';

    #[Groups(['kpi:read'])]
    public ?float $intensity = null;

    #[Groups(['kpi:read'])]
    public float $totalKwh = 0.0;
}
