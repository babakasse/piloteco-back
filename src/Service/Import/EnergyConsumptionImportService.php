<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\EnergyConsumption;
use App\Entity\Site;
use App\Repository\EnergyConsumptionRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class EnergyConsumptionImportService
{
    private const int BATCH_SIZE = 500;

    /** @var array<string, int> Map site_unique_code → site id (survives EM::clear()) */
    private array $siteIdCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRepository $siteRepository,
        private readonly EnergyConsumptionRepository $energyConsumptionRepository,
    ) {}

    public function import(string $csvFilePath): ImportStats
    {
        $handle = fopen($csvFilePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot open file: %s', $csvFilePath));
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or unreadable');
        }

        $stats = new ImportStats();
        $rowIndex = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $data = array_combine($headers, $row);
            if ($data === false) {
                $stats->incrementSkipped();
                continue;
            }

            $this->processRow($data, $stats);
            $rowIndex++;

            if ($rowIndex % self::BATCH_SIZE === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        fclose($handle);

        return $stats;
    }

    /**
     * @param array<string, string> $data
     */
    private function processRow(array $data, ImportStats $stats): void
    {
        $siteUniqueCode = trim($data['site_unique_code'] ?? '');
        $monthYear = $this->normalizeMonthYear($data['month_year'] ?? '');
        $resourceCategory = strtoupper(trim($data['resource_category'] ?? ''));

        if ($siteUniqueCode === '' || $monthYear === '' || $resourceCategory === '') {
            $stats->incrementSkipped();
            return;
        }

        $site = $this->findOrCreateSite(
            $siteUniqueCode,
            trim($data['site_country_code'] ?? ''),
        );

        $resourceSubCategory = trim($data['resource_sub_category'] ?? '') ?: null;

        $consumption = $this->energyConsumptionRepository->findBySiteMonthAndResource(
            $site,
            $monthYear,
            $resourceCategory,
            $resourceSubCategory,
        );

        if ($consumption === null) {
            $consumption = new EnergyConsumption();
            $consumption->setSite($site);
            $consumption->setMonthYear($monthYear);
            $consumption->setResourceCategory($resourceCategory);
            $consumption->setResourceSubCategory($resourceSubCategory);
            $this->entityManager->persist($consumption);
            $stats->incrementCreated();
        } else {
            $stats->incrementUpdated();
        }

        $consumption->setFoodSurfaceUnit($data['food_surface_resource_consumed_unit_measure'] ?? null ?: null);
        $consumption->setFoodSurfaceQuantityConsumed($this->parseFloat($data['food_surface_quantity_resource_consumed'] ?? ''));
        $consumption->setFoodSurfaceQuantityEstimated($this->parseFloat($data['food_surface_quantity_resource_estimated'] ?? ''));
        $consumption->setEstimatedFoodSurfaceFlag($this->parseBool($data['estimated_food_surface_resource_flag'] ?? ''));
        $consumption->setTotalSurfaceUnit($data['total_surface_resource_consumed_unit_measure'] ?? null ?: null);
        $consumption->setTotalSurfaceQuantityConsumed($this->parseFloat($data['total_surface_quantity_resource_consumed'] ?? ''));
        $consumption->setTotalSurfaceQuantityEstimated($this->parseFloat($data['total_surface_quantity_resource_estimated'] ?? ''));
        $consumption->setEstimatedTotalSurfaceFlag($this->parseBool($data['estimated_total_surface_resource_flag'] ?? ''));
        $consumption->setIsComparable($this->parseBool($data['is_comparable'] ?? 'true'));
    }

    private function findOrCreateSite(string $siteUniqueCode, string $countryCode): Site
    {
        if (isset($this->siteIdCache[$siteUniqueCode])) {
            /** @var Site $site */
            $site = $this->entityManager->getReference(Site::class, $this->siteIdCache[$siteUniqueCode]);
            return $site;
        }

        $site = $this->siteRepository->findBySiteUniqueCode($siteUniqueCode);

        if ($site === null) {
            $site = new Site();
            $site->setSiteUniqueCode($siteUniqueCode);
            $site->setCountryCode($countryCode);
            $this->entityManager->persist($site);
            $this->entityManager->flush();
        }

        $this->siteIdCache[$siteUniqueCode] = $site->getId();

        return $site;
    }

    private function normalizeMonthYear(string $value): string
    {
        $value = trim($value);

        // Handle "2024-01-01" → "2024-01"
        if (preg_match('/^(\d{4}-\d{2})-\d{2}$/', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    private function parseFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || $value === 'null' || $value === 'NULL') {
            return null;
        }
        return (float) $value;
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', '1', 'yes'], true);
    }
}
