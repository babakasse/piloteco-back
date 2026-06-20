<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiMonthlyEvolutionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiMonthlyEvolution',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/monthly-evolution',
            provider: KpiMonthlyEvolutionProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiMonthlyEvolutionResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $month = '';

    #[Groups(['kpi:read'])]
    public ?float $current = null;

    #[Groups(['kpi:read'])]
    public ?float $previous = null;
}
