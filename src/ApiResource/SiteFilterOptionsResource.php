<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\SiteFilterOptionsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'SiteFilterOptions',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/site-filter-options',
            provider: SiteFilterOptionsProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class SiteFilterOptionsResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $id = 'site-filter-options';

    /**
     * @var string[]
     */
    #[Groups(['kpi:read'])]
    public array $siteTypes = [];

    /**
     * @var string[]
     */
    #[Groups(['kpi:read'])]
    public array $siteFormats = [];
}
