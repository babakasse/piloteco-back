<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\RefrigerantFluid;
use App\Entity\Site;
use App\Repository\RefrigerantFluidRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RefrigerantFluidImportService
{
    private const int BATCH_SIZE = 500;

    /** @var array<string, int> Map site_unique_code → site id (survives EM::clear()) */
    private array $siteIdCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRepository $siteRepository,
        private readonly RefrigerantFluidRepository $refrigerantFluidRepository,
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
        $fluidType = trim($data['refrigerant_fluid_type'] ?? '');

        if ($siteUniqueCode === '' || $monthYear === '' || $fluidType === '') {
            $stats->incrementSkipped();
            return;
        }

        $site = $this->findOrCreateSite(
            $siteUniqueCode,
            trim($data['site_country_code'] ?? ''),
        );

        $fluid = $this->refrigerantFluidRepository->findBySiteMonthAndType(
            $site,
            $monthYear,
            $fluidType,
        );

        if ($fluid === null) {
            $fluid = new RefrigerantFluid();
            $fluid->setSite($site);
            $fluid->setMonthYear($monthYear);
            $fluid->setRefrigerantFluidType($fluidType);
            $this->entityManager->persist($fluid);
            $stats->incrementCreated();
        } else {
            $stats->incrementUpdated();
        }

        $fluid->setUnitOfMeasure($data['refrigerant_fluid_reloaded_unit_of_measure'] ?? null ?: null);
        $fluid->setQuantityReloaded($this->parseFloat($data['quantity_refrigerant_fluid_reloaded'] ?? ''));
        $fluid->setQuantityEstimated($this->parseFloat($data['quantity_refrigerant_fluid_estimated'] ?? ''));
        $fluid->setEstimatedValueFlag($this->parseBool($data['estimated_value_flag'] ?? ''));
        $fluid->setIsComparable($this->parseBool($data['is_comparable'] ?? 'true'));
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
        if (preg_match('/^(\d{4}-\d{2})-\d{2}$/', $value, $matches)) {
            return $matches[1];
        }
        return $value;
    }

    private function parseFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || strcasecmp($value, 'null') === 0) {
            return null;
        }
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', '1', 'yes'], true);
    }
}
