<?php

namespace App\Tests\Unit\Service;

use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\Emission;
use App\Repository\EmissionRepository;
use App\Service\EmissionCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class EmissionCalculationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EmissionRepository $emissionRepository;
    private EmissionCalculationService $service;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emissionRepository = $this->createMock(EmissionRepository::class);
        $this->service = new EmissionCalculationService($this->entityManager, $this->emissionRepository);
    }

    public function testCalculateEmissions(): void
    {
        // Test que la méthode de calcul fonctionne correctement avec tCO2e
        $activityData = 100.0;
        $emissionFactor = 0.05; // Facteur en tCO2e par unité
        $expectedEmissions = 5.0; // 100 * 0.05 = 5.0 tCO2e

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
            0.5, // Emission factor in tCO2e per unit
            1,
            'tCO2e',
            'Test Description'
        );

        // Verify the emission was created correctly
        $this->assertEquals('Test Source', $emission->getSource());
        $this->assertEquals('Test Category', $emission->getCategory());
        $this->assertEquals(50.0, $emission->getAmount()); // 100 * 0.5 = 50.0 tCO2e
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

    /**
     * @throws Exception
     */
    public function testGetEmissionsByScope(): void
    {
        // Create test objects avec de vraies émissions au lieu de mocks
        $assessment = new CarbonAssessment();
        $assessment->setName('Test Assessment');

        // Create real emissions with different scopes
        $emission1 = new Emission();
        $emission1->setScope(1);
        $emission1->setAmount(10.0);
        $emission1->setSource('Test Source 1');
        $emission1->setCategory('Test Category 1');
        $emission1->setAssessment($assessment);

        $emission2 = new Emission();
        $emission2->setScope(2);
        $emission2->setAmount(20.0);
        $emission2->setSource('Test Source 2');
        $emission2->setCategory('Test Category 2');
        $emission2->setAssessment($assessment);

        $emission3 = new Emission();
        $emission3->setScope(3);
        $emission3->setAmount(30.0);
        $emission3->setSource('Test Source 3');
        $emission3->setCategory('Test Category 3');
        $emission3->setAssessment($assessment);

        // Add emissions to the assessment
        $assessment->addEmission($emission1);
        $assessment->addEmission($emission2);
        $assessment->addEmission($emission3);

        // Call the method
        $result = $this->service->getEmissionsByScope($assessment);

        // Verify the result
        $this->assertEquals([
            1 => 10.0,
            2 => 20.0,
            3 => 30.0
        ], $result);
    }
}
