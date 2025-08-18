<?php

namespace App\Tests\Unit\Entity;

use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\Emission;
use PHPUnit\Framework\TestCase;

class CarbonAssessmentTest extends TestCase
{
    private CarbonAssessment $assessment;
    private Company $company;

    protected function setUp(): void
    {
        $this->company = new Company();
        $this->company->setName('Test Company');
        
        $this->assessment = new CarbonAssessment();
        $this->assessment->setName('Test Assessment');
        $this->assessment->setCompany($this->company);
        $this->assessment->setAssessmentDate(new \DateTime());
    }

    public function testCalculateEmissions(): void
    {
        // Create emissions with different scopes
        $emission1 = new Emission();
        $emission1->setSource('Source 1');
        $emission1->setCategory('Category 1');
        $emission1->setAmount(10.0);
        $emission1->setScope(1);
        $emission1->setAssessment($this->assessment);
        
        $emission2 = new Emission();
        $emission2->setSource('Source 2');
        $emission2->setCategory('Category 2');
        $emission2->setAmount(20.0);
        $emission2->setScope(2);
        $emission2->setAssessment($this->assessment);
        
        $emission3 = new Emission();
        $emission3->setSource('Source 3');
        $emission3->setCategory('Category 3');
        $emission3->setAmount(30.0);
        $emission3->setScope(3);
        $emission3->setAssessment($this->assessment);
        
        // Add emissions to assessment
        $this->assessment->addEmission($emission1);
        $this->assessment->addEmission($emission2);
        $this->assessment->addEmission($emission3);
        
        // Calculate emissions
        $this->assessment->calculateEmissions();
        
        // Verify the results
        $this->assertEquals(10.0, $this->assessment->getScope1Emissions());
        $this->assertEquals(20.0, $this->assessment->getScope2Emissions());
        $this->assertEquals(30.0, $this->assessment->getScope3Emissions());
        $this->assertEquals(60.0, $this->assessment->getTotalEmissions());
    }

    public function testCalculateEmissionsWithNoEmissions(): void
    {
        // Calculate emissions with no emissions added
        $this->assessment->calculateEmissions();
        
        // Verify the results
        $this->assertEquals(0.0, $this->assessment->getScope1Emissions());
        $this->assertEquals(0.0, $this->assessment->getScope2Emissions());
        $this->assertEquals(0.0, $this->assessment->getScope3Emissions());
        $this->assertEquals(0.0, $this->assessment->getTotalEmissions());
    }

    public function testAddAndRemoveEmission(): void
    {
        // Create an emission
        $emission = new Emission();
        $emission->setSource('Test Source');
        $emission->setCategory('Test Category');
        $emission->setAmount(10.0);
        $emission->setScope(1);
        
        // Add emission to assessment
        $this->assessment->addEmission($emission);
        
        // Verify the emission was added
        $this->assertCount(1, $this->assessment->getEmissions());
        $this->assertSame($this->assessment, $emission->getAssessment());
        
        // Remove emission from assessment
        $this->assessment->removeEmission($emission);
        
        // Verify the emission was removed
        $this->assertCount(0, $this->assessment->getEmissions());
        $this->assertNull($emission->getAssessment());
    }

    public function testUpdateCalculationsLifecycleCallback(): void
    {
        // Create a mock assessment that we can partially mock to test the lifecycle callback
        $assessment = $this->getMockBuilder(CarbonAssessment::class)
            ->onlyMethods(['calculateEmissions'])
            ->getMock();
        
        // Expect calculateEmissions to be called once
        $assessment->expects($this->once())
            ->method('calculateEmissions');
        
        // Trigger the lifecycle callback
        $reflectionClass = new \ReflectionClass(CarbonAssessment::class);
        $method = $reflectionClass->getMethod('updateCalculations');
        $method->setAccessible(true);
        $method->invoke($assessment);
    }

    public function testGettersAndSetters(): void
    {
        // Test name getter and setter
        $this->assessment->setName('New Name');
        $this->assertEquals('New Name', $this->assessment->getName());
        
        // Test description getter and setter
        $this->assessment->setDescription('New Description');
        $this->assertEquals('New Description', $this->assessment->getDescription());
        
        // Test status getter and setter
        $this->assessment->setStatus('published');
        $this->assertEquals('published', $this->assessment->getStatus());
        
        // Test company getter and setter
        $newCompany = new Company();
        $newCompany->setName('New Company');
        $this->assessment->setCompany($newCompany);
        $this->assertSame($newCompany, $this->assessment->getCompany());
        
        // Test assessment date getter and setter
        $newDate = new \DateTime('2023-01-01');
        $this->assessment->setAssessmentDate($newDate);
        $this->assertSame($newDate, $this->assessment->getAssessmentDate());
        
        // Test year is set automatically from assessment date
        $this->assertEquals(2023, $this->assessment->getYear());
    }
}