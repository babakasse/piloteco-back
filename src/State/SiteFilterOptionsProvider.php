<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SiteFilterOptionsResource;
use App\Repository\SiteRepository;

final readonly class SiteFilterOptionsProvider implements ProviderInterface
{
    public function __construct(
        private SiteRepository $siteRepository,
    ) {}

    /**
     * @return list<SiteFilterOptionsResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $resource = new SiteFilterOptionsResource();
        $resource->siteTypes = $this->siteRepository->findDistinctSiteTypes();
        $resource->siteFormats = $this->siteRepository->findDistinctSiteFormats();

        return [$resource];
    }
}
