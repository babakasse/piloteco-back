<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiSiteRankingProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiSiteRanking',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/site-ranking',
            provider: KpiSiteRankingProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiSiteRankingResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public int $rank = 0;

    #[Groups(['kpi:read'])]
    public string $siteUniqueCode = '';

    #[Groups(['kpi:read'])]
    public string $countryCode = '';

    #[Groups(['kpi:read'])]
    public float $intensity = 0.0;

    #[Groups(['kpi:read'])]
    public ?float $evolutionPercent = null;
}
