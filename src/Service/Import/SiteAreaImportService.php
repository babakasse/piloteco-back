<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Site;
use App\Entity\SiteArea;
use App\Repository\SiteAreaRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SiteAreaImportService
{
    private const int BATCH_SIZE = 500;

    /** @var array<string, int> Map site_unique_code → site id (survives EM::clear()) */
    private array $siteIdCache = [];

    /** @var array<string, true> Set of "siteId-year-month" keys to deduplicate CSV rows */
    private array $processedAreaKeys = [];

    /**
     * Surface CSV column indexes (0-based):
     *  0 → fiscal_year_type (FY24, FY25…)
     *  1 → year (numeric)
     *  2 → month_number
     *  3 → month_label (ignored)
     *  4 → site_unique_code
     *  5 → site_label
     *  6 → sales_area_m2
     *  7 → total_area_m2
     */
    private const int COL_FISCAL_YEAR = 1;
    private const int COL_MONTH = 2;
    private const int COL_SITE_CODE = 4;
    private const int COL_SITE_LABEL = 5;
    private const int COL_SALES_AREA = 6;
    private const int COL_TOTAL_AREA = 7;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRepository $siteRepository,
        private readonly SiteAreaRepository $siteAreaRepository,
    ) {}

    public function import(string $csvFilePath): ImportStats
    {
        $handle = fopen($csvFilePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot open file: %s', $csvFilePath));
        }

        // Skip header row
        fgetcsv($handle, 0, ',', '"', '');

        $stats = new ImportStats();
        $rowIndex = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $this->processRow($row, $stats);
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
     * @param array<int, string> $row
     */
    private function processRow(array $row, ImportStats $stats): void
    {
        $siteUniqueCode = trim($row[self::COL_SITE_CODE] ?? '');
        $fiscalYear = (int) ($row[self::COL_FISCAL_YEAR] ?? 0);
        $month = $this->parseMonth($row[self::COL_MONTH] ?? '');

        if ($siteUniqueCode === '' || $fiscalYear === 0 || $month < 1 || $month > 12) {
            $stats->incrementSkipped();
            return;
        }

        $salesArea = $this->parseFloat($row[self::COL_SALES_AREA] ?? '');
        $totalArea = $this->parseFloat($row[self::COL_TOTAL_AREA] ?? '');

        if ($salesArea === null && $totalArea === null) {
            $stats->incrementSkipped();
            return;
        }

        // Deduplicate: skip if same (site, year, month) already seen in this run
        $areaKey = "{$siteUniqueCode}-{$fiscalYear}-{$month}";
        if (isset($this->processedAreaKeys[$areaKey])) {
            $stats->incrementSkipped();
            return;
        }
        $this->processedAreaKeys[$areaKey] = true;

        $site = $this->findOrCreateSite(
            $siteUniqueCode,
            $row[self::COL_SITE_LABEL] ?? null,
        );

        $area = $this->siteAreaRepository->findBySiteYearMonth($site, $fiscalYear, $month);

        if ($area === null) {
            $area = new SiteArea();
            $area->setSite($site);
            $area->setFiscalYear($fiscalYear);
            $area->setMonth($month);
            $this->entityManager->persist($area);
            $stats->incrementCreated();
        } else {
            $stats->incrementUpdated();
        }

        $area->setSalesAreaM2($salesArea);
        $area->setTotalAreaM2($totalArea);
    }

    private function findOrCreateSite(string $siteUniqueCode, ?string $label): Site
    {
        // After EM::clear(), re-fetch by ID to avoid working with detached entities
        if (isset($this->siteIdCache[$siteUniqueCode])) {
            /** @var Site $site */
            $site = $this->entityManager->getReference(Site::class, $this->siteIdCache[$siteUniqueCode]);
            return $site;
        }

        $site = $this->siteRepository->findBySiteUniqueCode($siteUniqueCode);

        if ($site === null) {
            $site = new Site();
            $site->setSiteUniqueCode($siteUniqueCode);
            $site->setCountryCode($this->extractCountryCode($siteUniqueCode));
            $site->setLabel($label ? trim($label) : null);
            $this->entityManager->persist($site);
            $this->entityManager->flush();
        } elseif ($label !== null && $site->getLabel() === null) {
            $site->setLabel(trim($label));
        }

        $this->siteIdCache[$siteUniqueCode] = $site->getId();

        return $site;
    }

    /**
     * Parse "M01" → 1, "M12" → 12, or plain "1" → 1
     */
    private function parseMonth(string $value): int
    {
        $value = trim($value);
        if (preg_match('/^M(\d{1,2})$/', $value, $matches)) {
            return (int) $matches[1];
        }
        return (int) $value;
    }

    private function extractCountryCode(string $siteUniqueCode): string
    {
        // "FR_069_MAG" → "FR", "PDG_001" → "PDG"
        if (str_contains($siteUniqueCode, '_')) {
            return strtoupper(explode('_', $siteUniqueCode, 2)[0]);
        }

        // "FRSU01123" → "FR", "RU0074" → "RU", "HU00300" → "HU"
        preg_match('/^([A-Za-z]+)/', $siteUniqueCode, $matches);
        $prefix = strtoupper($matches[1] ?? $siteUniqueCode);

        // Known multi-letter country codes (PDG = Congo)
        $knownCodes = ['PDG', 'CI', 'SN'];
        foreach ($knownCodes as $code) {
            if (str_starts_with($prefix, $code)) {
                return $code;
            }
        }

        // Default: take first 2 chars (ISO alpha-2)
        return substr($prefix, 0, 2);
    }

    private function parseFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || $value === 'null' || $value === 'NULL') {
            return null;
        }
        // Handle comma as decimal separator
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }
}
