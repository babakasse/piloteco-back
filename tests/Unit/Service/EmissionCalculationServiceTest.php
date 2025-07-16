<?php

namespace App\Tests\Unit\Service;

use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\Emission;
use App\Repository\EmissionRepository;
use App\Service\EmissionCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EmissionCalculationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EmissionRepository $emissionRepository;
    private EmissionCalculationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emissionRepository = $this->createMock(EmissionRepository::class);
        $this->service = new EmissionCalculationService($this->entityManager, $this->emissionRepository);
    }

    public function testCalculateEmissions(): void
    {
        // Test that the calculation method works correctly
        $activityData = 100.0;
        $emissionFactor = 0.5;
        $expectedEmissions = 0.05; // 100 * 0.5 / 1000 = 0.05 tCO2e

        $result = $this->service->calculateEmissions($activityData, $emissionFactor);

        $this->assertEquals($expectedEmissions, $result);
    }

    public function testAddEmissionWithCalculation(): void
    {
        // Create test objects
        $company = new Company();
        $company->setName('Test Company');

        $assessment = $this->createMock(CarbonAssessment::class);
        $assessment->method('getId')->willReturn(1); // Mock getId to return a valid ID
        $assessment->method('getName')->willReturn('Test Assessment');

        // Mock the repository's calculateByScope method to return 0
        $this->emissionRepository->method('calculateByScope')
            ->willReturn(0.0);

        // Set up expectations for entity manager
        // The persist method will be called at least once for the emission
        // and possibly again for the assessment in calculateEmissionsForAssessment
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');

        // The flush method will be called at least once
        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        // Call the method
        $emission = $this->service->addEmissionWithCalculation(
            $assessment,
            'Test Source',
            'Test Category',
            100.0,
            0.5,
            1,
            'tCO2e',
            'Test Description'
        );

        // Verify the emission was created correctly
        $this->assertEquals('Test Source', $emission->getSource());
        $this->assertEquals('Test Category', $emission->getCategory());
        $this->assertEquals(0.05, $emission->getAmount());
        $this->assertEquals(1, $emission->getScope());
        $this->assertEquals('tCO2e', $emission->getUnit());
        $this->assertEquals('Test Description', $emission->getDescription());
        $this->assertSame($assessment, $emission->getAssessment());
    }

    public function testGetEmissionsByCategory(): void
    {
        // Create test objects
        $assessment = new CarbonAssessment();
        $assessment->setName('Test Assessment');

        // Create emissions with different categories
        $emission1 = new Emission();
        $emission1->setSource('Source 1');
        $emission1->setCategory('Category 1');
        $emission1->setAmount(10.0);
        $emission1->setScope(1);
        $emission1->setAssessment($assessment);

        $emission2 = new Emission();
        $emission2->setSource('Source 2');
        $emission2->setCategory('Category 2');
        $emission2->setAmount(20.0);
        $emission2->setScope(2);
        $emission2->setAssessment($assessment);

        $emission3 = new Emission();
        $emission3->setSource('Source 3');
        $emission3->setCategory('Category 1');
        $emission3->setAmount(30.0);
        $emission3->setScope(3);
        $emission3->setAssessment($assessment);

        // Add emissions to assessment
        $assessment->addEmission($emission1);
        $assessment->addEmission($emission2);
        $assessment->addEmission($emission3);

        // Call the method
        $result = $this->service->getEmissionsByCategory($assessment);

        // Verify the result
        $this->assertEquals([
            'Category 1' => 40.0,
            'Category 2' => 20.0,
        ], $result);
    }

    public function testGetEmissionsByScope(): void
    {
        // Create test objects
        $assessment = new CarbonAssessment();
        $assessment->setName('Test Assessment');
        $assessment->setScope1Emissions(10.0);
        $assessment->setScope2Emissions(20.0);
        $assessment->setScope3Emissions(30.0);

        // Call the method
        $result = $this->service->getEmissionsByScope($assessment);

        // Verify the result
        $this->assertEquals([
            1 => 10.0,
            2 => 20.0,
            3 => 30.0,
        ], $result);
    }
}
