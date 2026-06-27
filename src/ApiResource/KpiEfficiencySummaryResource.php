<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\KpiEfficiencySummaryProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'KpiEfficiencySummary',
    operations: [
        new GetCollection(
            uriTemplate: '/kpi/efficiency-summary',
            provider: KpiEfficiencySummaryProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['kpi:read']],
    paginationEnabled: false,
)]
final class KpiEfficiencySummaryResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['kpi:read'])]
    public string $id = 'efficiency-summary';

    // ── YTD — All site types ──────────────────────────────────────────────────

    #[Groups(['kpi:read'])]
    public ?float $ytdAllElecKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdAllGasNgKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdAllGasHnKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdAllElecGasKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdAllWaterConsumedM3 = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdAllWaterStoredM3 = null;

    // ── YTD — Site type MAG ───────────────────────────────────────────────────

    #[Groups(['kpi:read'])]
    public ?float $ytdMagElecKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdMagGasNgKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdMagGasHnKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdMagElecGasKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdMagWaterConsumedM3 = null;

    #[Groups(['kpi:read'])]
    public ?float $ytdMagWaterStoredM3 = null;

    // ── MTD — All site types ──────────────────────────────────────────────────

    #[Groups(['kpi:read'])]
    public ?float $mtdAllElecKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdAllGasNgKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdAllGasHnKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdAllElecGasKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdAllWaterConsumedM3 = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdAllWaterStoredM3 = null;

    // ── MTD — Site type MAG ───────────────────────────────────────────────────

    #[Groups(['kpi:read'])]
    public ?float $mtdMagElecKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdMagGasNgKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdMagGasHnKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdMagElecGasKwh = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdMagWaterConsumedM3 = null;

    #[Groups(['kpi:read'])]
    public ?float $mtdMagWaterStoredM3 = null;
}
