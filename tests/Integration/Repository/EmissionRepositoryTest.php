<?php

namespace App\Tests\Integration\Repository;

use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\Emission;
use App\Repository\EmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EmissionRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?EmissionRepository $repository = null;
    private ?Company $company = null;
    private ?CarbonAssessment $assessment = null;
    private array $emissions = [];

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(Emission::class);

        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create a company
        $company = new Company();
        $company->setName('Repository Test Company')
            ->setAddress('123 Repository Street')
            ->setSector('Technology');
        $this->entityManager->persist($company);
        $this->company = $company;

        // Create an assessment
        $assessment = new CarbonAssessment();
        $assessment->setName('Repository Test Assessment')
            ->setDescription('Assessment for repository tests')
            ->setAssessmentDate(new \DateTime())
            ->setStatus('draft')
            ->setCompany($company);
        $this->entityManager->persist($assessment);
        $this->assessment = $assessment;

        // Create emissions with different scopes and categories
        $categories = ['Energy', 'Transportation', 'Waste', 'Materials'];
        $scopes = [1, 2, 3];

        foreach ($categories as $category) {
            foreach ($scopes as $scope) {
                $emission = new Emission();
                $emission->setSource("Source for $category")
                    ->setCategory($category)
                    ->setDescription("Description for $category, scope $scope")
                    ->setAmount(10.0 * $scope) // Different amount for each scope
                    ->setUnit('tCO2e')
                    ->setScope($scope)
                    ->setAssessment($assessment);
                $this->entityManager->persist($emission);
                $this->emissions[] = $emission;
            }
        }

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        // Remove test data
        foreach ($this->emissions as $emission) {
            $this->entityManager->remove($emission);
        }
        if ($this->assessment) {
            $this->entityManager->remove($this->assessment);
        }
        if ($this->company) {
            $this->entityManager->remove($this->company);
        }
        $this->entityManager->flush();

        parent::tearDown();
        
        // Avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testFindByAssessment(): void
    {
        $emissions = $this->repository->findByAssessment($this->assessment->getId());
        
        // We created 12 emissions (4 categories * 3 scopes)
        $this->assertCount(12, $emissions);
        
        // Check that all emissions belong to the assessment
        foreach ($emissions as $emission) {
            $this->assertSame($this->assessment, $emission->getAssessment());
        }
    }

    public function testFindByScope(): void
    {
        // Test for each scope
        for ($scope = 1; $scope <= 3; $scope++) {
            $emissions = $this->repository->findByScope($scope);
            
            // We should have at least 4 emissions per scope (one for each category)
            $this->assertGreaterThanOrEqual(4, count($emissions));
            
            // Check that all emissions have the correct scope
            foreach ($emissions as $emission) {
                $this->assertEquals($scope, $emission->getScope());
            }
        }
    }

    public function testFindByAssessmentAndScope(): void
    {
        // Test for each scope
        for ($scope = 1; $scope <= 3; $scope++) {
            $emissions = $this->repository->findByAssessmentAndScope($this->assessment->getId(), $scope);
            
            // We should have 4 emissions per scope for this assessment (one for each category)
            $this->assertCount(4, $emissions);
            
            // Check that all emissions have the correct scope and assessment
            foreach ($emissions as $emission) {
                $this->assertEquals($scope, $emission->getScope());
                $this->assertSame($this->assessment, $emission->getAssessment());
            }
        }
    }

    public function testFindByCategory(): void
    {
        $categories = ['Energy', 'Transportation', 'Waste', 'Materials'];
        
        // Test for each category
        foreach ($categories as $category) {
            $emissions = $this->repository->findByCategory($category);
            
            // We should have at least 3 emissions per category (one for each scope)
            $this->assertGreaterThanOrEqual(3, count($emissions));
            
            // Check that all emissions have the correct category
            foreach ($emissions as $emission) {
                $this->assertEquals($category, $emission->getCategory());
            }
        }
    }

    public function testCalculateTotalForAssessment(): void
    {
        $total = $this->repository->calculateTotalForAssessment($this->assessment->getId());
        
        // Calculate the expected total: 10*1 + 10*2 + 10*3 = 60 per category, 60*4 = 240 total
        $expectedTotal = 240.0;
        
        $this->assertEquals($expectedTotal, $total);
    }

    public function testCalculateByScope(): void
    {
        // Test for each scope
        for ($scope = 1; $scope <= 3; $scope++) {
            $total = $this->repository->calculateByScope($this->assessment->getId(), $scope);
            
            // Calculate the expected total: 10*scope*4 categories
            $expectedTotal = 10.0 * $scope * 4;
            
            $this->assertEquals($expectedTotal, $total);
        }
    }
}