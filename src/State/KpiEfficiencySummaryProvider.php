<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\KpiEfficiencySummaryResource;
use App\Service\EnergyKpiCalculatorService;

final readonly class KpiEfficiencySummaryProvider implements ProviderInterface
{
    use KpiFilterResolverTrait;

    public function __construct(
        private EnergyKpiCalculatorService $kpiService,
    ) {}

    /**
     * @return list<KpiEfficiencySummaryResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $filters = $context['filters'] ?? [];

        $month = (string) ($filters['month'] ?? date('Y-m'));
        $countryCodes = $this->resolveCountryCodes($filters);
        $onlyComparable = $this->resolveComparable($filters);
        $realDataOnly = $this->resolveRealDataOnly($filters);

        $data = $this->kpiService->computeEfficiencySummary(
            $month,
            $countryCodes,
            $onlyComparable,
            $realDataOnly,
        );

        $resource = new KpiEfficiencySummaryResource();

        $resource->ytdAllElecKwh = $data['ytd']['all']['elec_kwh'];
        $resource->ytdAllGasNgKwh = $data['ytd']['all']['gas_ng_kwh'];
        $resource->ytdAllGasHnKwh = $data['ytd']['all']['gas_hn_kwh'];
        $resource->ytdAllElecGasKwh = $data['ytd']['all']['elec_gas_kwh'];
        $resource->ytdAllWaterConsumedM3 = $data['ytd']['all']['water_consumed_m3'];
        $resource->ytdAllWaterStoredM3 = $data['ytd']['all']['water_stored_m3'];

        $resource->ytdMagElecKwh = $data['ytd']['mag']['elec_kwh'];
        $resource->ytdMagGasNgKwh = $data['ytd']['mag']['gas_ng_kwh'];
        $resource->ytdMagGasHnKwh = $data['ytd']['mag']['gas_hn_kwh'];
        $resource->ytdMagElecGasKwh = $data['ytd']['mag']['elec_gas_kwh'];
        $resource->ytdMagWaterConsumedM3 = $data['ytd']['mag']['water_consumed_m3'];
        $resource->ytdMagWaterStoredM3 = $data['ytd']['mag']['water_stored_m3'];

        $resource->mtdAllElecKwh = $data['mtd']['all']['elec_kwh'];
        $resource->mtdAllGasNgKwh = $data['mtd']['all']['gas_ng_kwh'];
        $resource->mtdAllGasHnKwh = $data['mtd']['all']['gas_hn_kwh'];
        $resource->mtdAllElecGasKwh = $data['mtd']['all']['elec_gas_kwh'];
        $resource->mtdAllWaterConsumedM3 = $data['mtd']['all']['water_consumed_m3'];
        $resource->mtdAllWaterStoredM3 = $data['mtd']['all']['water_stored_m3'];

        $resource->mtdMagElecKwh = $data['mtd']['mag']['elec_kwh'];
        $resource->mtdMagGasNgKwh = $data['mtd']['mag']['gas_ng_kwh'];
        $resource->mtdMagGasHnKwh = $data['mtd']['mag']['gas_hn_kwh'];
        $resource->mtdMagElecGasKwh = $data['mtd']['mag']['elec_gas_kwh'];
        $resource->mtdMagWaterConsumedM3 = $data['mtd']['mag']['water_consumed_m3'];
        $resource->mtdMagWaterStoredM3 = $data['mtd']['mag']['water_stored_m3'];

        return [$resource];
    }
}
