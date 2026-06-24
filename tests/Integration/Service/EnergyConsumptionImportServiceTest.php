<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\EnergyConsumption;
use App\Entity\Site;
use App\Service\Import\EnergyConsumptionImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EnergyConsumptionImportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private EnergyConsumptionImportService $importService;
    private string $tmpDir;
    /** @var string[] */
    private array $createdSiteCodes = [];

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->importService = $container->get(EnergyConsumptionImportService::class);
        $this->tmpDir = sys_get_temp_dir();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdSiteCodes as $code) {
            $site = $this->entityManager->getRepository(Site::class)->findOneBy(['siteUniqueCode' => $code]);
            if ($site !== null) {
                $this->entityManager->remove($site);
            }
        }
        $this->entityManager->flush();
        $this->createdSiteCodes = [];

        parent::tearDown();
        $this->entityManager->close();
    }

    private function makeCsvFile(string $suffix, string $content): string
    {
        $path = $this->tmpDir . '/piloteco_test_' . $suffix . '_' . uniqid() . '.csv';
        file_put_contents($path, $content);
        return $path;
    }

    private function uid(string $base): string
    {
        $code = $base . '_' . strtoupper(substr(uniqid(), -6));
        $this->createdSiteCodes[] = $code;
        return $code;
    }

    private function csvHeaders(): string
    {
        return "month_year,site_country_code,site_unique_code,resource_category,resource_sub_category,"
            . "food_surface_resource_consumed_unit_measure,food_surface_quantity_resource_consumed,"
            . "food_surface_quantity_resource_estimated,estimated_food_surface_resource_flag,"
            . "total_surface_resource_consumed_unit_measure,total_surface_quantity_resource_consumed,"
            . "total_surface_quantity_resource_estimated,estimated_total_surface_resource_flag,is_comparable\n";
    }

    public function testImportCreatesNewSitesAndConsumptions(): void
    {
        $frCode = $this->uid('IMPT_FR');
        $esCode = $this->uid('IMPT_ES');

        $csv = $this->csvHeaders()
            . "2024-01-01,FR,{$frCode},ELEC,,kWh,50000,0,false,kWh,100000,0,false,true\n"
            . "2024-01-01,ES,{$esCode},ELEC,,kWh,30000,0,false,kWh,60000,0,false,true\n"
            . "2024-02-01,FR,{$frCode},GAS,,kWh,,0,false,kWh,40000,0,false,true\n";

        $stats = $this->importService->import($this->makeCsvFile('create', $csv));

        $this->assertSame(3, $stats->getCreated());
        $this->assertSame(0, $stats->getUpdated());
        $this->assertSame(0, $stats->getSkipped());

        $site = $this->entityManager->getRepository(Site::class)->findOneBy(['siteUniqueCode' => $frCode]);
        $this->assertNotNull($site);
        $this->assertSame('FR', $site->getCountryCode());

        $consumption = $this->entityManager->getRepository(EnergyConsumption::class)
            ->findOneBy(['site' => $site, 'monthYear' => '2024-01', 'resourceCategory' => 'ELEC']);
        $this->assertNotNull($consumption);
        $this->assertSame(100_000.0, $consumption->getTotalSurfaceQuantityConsumed());
    }

    public function testImportUpdatesExistingConsumption(): void
    {
        $siteCode = $this->uid('UPSERT');

        $csv1 = $this->csvHeaders()
            . "2024-03-01,FR,{$siteCode},ELEC,,kWh,50000,0,false,kWh,100000,0,false,true\n";
        $this->importService->import($this->makeCsvFile('upsert1', $csv1));

        $csv2 = $this->csvHeaders()
            . "2024-03-01,FR,{$siteCode},ELEC,,kWh,55000,0,false,kWh,110000,0,false,true\n";
        $stats = $this->importService->import($this->makeCsvFile('upsert2', $csv2));

        $this->assertSame(0, $stats->getCreated());
        $this->assertSame(1, $stats->getUpdated());

        $this->entityManager->clear();
        $site = $this->entityManager->getRepository(Site::class)->findOneBy(['siteUniqueCode' => $siteCode]);
        $consumption = $this->entityManager->getRepository(EnergyConsumption::class)
            ->findOneBy(['site' => $site, 'monthYear' => '2024-03', 'resourceCategory' => 'ELEC']);

        $this->assertSame(110_000.0, $consumption->getTotalSurfaceQuantityConsumed());
    }

    public function testImportSkipsRowsWithMissingRequiredFields(): void
    {
        $csv = $this->csvHeaders()
            . ",FR,,ELEC,,kWh,50000,0,false,kWh,100000,0,false,true\n"  // missing site code
            . "2024-01-01,FR,SKIP_NORESO_X,,,,,,false,,,,false,true\n"; // missing resource

        $stats = $this->importService->import($this->makeCsvFile('skip', $csv));

        $this->assertSame(0, $stats->getCreated());
        $this->assertSame(2, $stats->getSkipped());
    }

    public function testImportNormalizesFullDateToMonthYear(): void
    {
        $siteCode = $this->uid('NORM');

        $csv = $this->csvHeaders()
            . "2024-05-01,FR,{$siteCode},ELEC,,kWh,50000,0,false,kWh,100000,0,false,true\n";
        $this->importService->import($this->makeCsvFile('normalize', $csv));

        $site = $this->entityManager->getRepository(Site::class)->findOneBy(['siteUniqueCode' => $siteCode]);
        $consumption = $this->entityManager->getRepository(EnergyConsumption::class)
            ->findOneBy(['site' => $site, 'monthYear' => '2024-05']);

        $this->assertNotNull($consumption, '"2024-05-01" should be normalized to month "2024-05"');
    }
}
