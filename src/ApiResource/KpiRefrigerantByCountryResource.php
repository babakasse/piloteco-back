<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiRefrigerantByCountryProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiRefrigerantByCountry',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/refrigerant-by-country',
            provider: KpiRefrigerantByCountryProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiRefrigerantByCountryResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $countryCode = '';

    #[Groups(['kpi:read'])]
    public float $totalKg = 0.0;

    #[Groups(['kpi:read'])]
    public string $quarterStart = '';

    #[Groups(['kpi:read'])]
    public string $quarterEnd = '';
}
