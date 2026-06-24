<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiRefrigerantByQuarterProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiRefrigerantByQuarter',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/refrigerant-by-quarter',
            provider: KpiRefrigerantByQuarterProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiRefrigerantByQuarterResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $id = '';

    #[Groups(['kpi:read'])]
    public string $quarter = '';

    #[Groups(['kpi:read'])]
    public string $quarterStart = '';

    #[Groups(['kpi:read'])]
    public string $quarterEnd = '';

    #[Groups(['kpi:read'])]
    public string $countryCode = '';

    #[Groups(['kpi:read'])]
    public float $totalKg = 0.0;
}
