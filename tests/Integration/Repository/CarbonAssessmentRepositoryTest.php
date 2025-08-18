<?php

namespace App\Tests\Integration\Repository;

use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Repository\CarbonAssessmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CarbonAssessmentRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?CarbonAssessmentRepository $repository = null;
    private array $companies = [];
    private array $assessments = [];

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(CarbonAssessment::class);

        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create companies
        for ($i = 1; $i <= 3; $i++) {
            $company = new Company();
            $company->setName("Company $i")
                ->setAddress("Address $i")
                ->setSector("Sector $i");
            $this->entityManager->persist($company);
            $this->companies[] = $company;
        }

        $this->entityManager->flush();

        // Create assessments for each company
        $years = [2021, 2022, 2023];
        $statuses = ['draft', 'published'];

        foreach ($this->companies as $company) {
            foreach ($years as $year) {
                // Create 1-2 assessments per year per company
                $numAssessments = rand(1, 2);
                for ($i = 1; $i <= $numAssessments; $i++) {
                    $assessment = new CarbonAssessment();
                    $assessment->setName("Assessment $i for " . $company->getName() . " in $year")
                        ->setDescription("Description for assessment $i")
                        ->setAssessmentDate(new \DateTime("$year-01-01"))
                        ->setStatus($statuses[array_rand($statuses)])
                        ->setCompany($company);
                    $this->entityManager->persist($assessment);
                    $this->assessments[] = $assessment;
                }
            }
        }

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        // Remove test data
        foreach ($this->assessments as $assessment) {
            $this->entityManager->remove($assessment);
        }
        $this->entityManager->flush();

        foreach ($this->companies as $company) {
            $this->entityManager->remove($company);
        }
        $this->entityManager->flush();

        parent::tearDown();
        
        // Avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testFindByCompany(): void
    {
        foreach ($this->companies as $company) {
            $assessments = $this->repository->findByCompany($company->getId());
            
            // Each company should have 3-6 assessments (1-2 per year for 3 years)
            $this->assertGreaterThanOrEqual(3, count($assessments));
            $this->assertLessThanOrEqual(6, count($assessments));
            
            // Check that all assessments belong to the company
            foreach ($assessments as $assessment) {
                $this->assertSame($company, $assessment->getCompany());
            }
        }
    }

    public function testFindByYear(): void
    {
        $years = [2021, 2022, 2023];
        
        foreach ($years as $year) {
            $assessments = $this->repository->findByYear($year);
            
            // Each year should have 3-6 assessments (1-2 per company for 3 companies)
            $this->assertGreaterThanOrEqual(3, count($assessments));
            $this->assertLessThanOrEqual(6, count($assessments));
            
            // Check that all assessments are for the correct year
            foreach ($assessments as $assessment) {
                $this->assertEquals($year, $assessment->getYear());
            }
        }
    }

    public function testFindByCompanyAndYear(): void
    {
        $years = [2021, 2022, 2023];
        
        foreach ($this->companies as $company) {
            foreach ($years as $year) {
                $assessments = $this->repository->findByCompanyAndYear($company->getId(), $year);
                
                // Each company should have 1-2 assessments per year
                $this->assertGreaterThanOrEqual(1, count($assessments));
                $this->assertLessThanOrEqual(2, count($assessments));
                
                // Check that all assessments belong to the company and year
                foreach ($assessments as $assessment) {
                    $this->assertSame($company, $assessment->getCompany());
                    $this->assertEquals($year, $assessment->getYear());
                }
            }
        }
    }

    public function testFindLatestForCompany(): void
    {
        foreach ($this->companies as $company) {
            $assessment = $this->repository->findLatestForCompany($company->getId());
            
            // Check that we got an assessment
            $this->assertNotNull($assessment);
            
            // Check that it belongs to the company
            $this->assertSame($company, $assessment->getCompany());
            
            // Check that it's the latest one (should be from 2023)
            $this->assertEquals(2023, $assessment->getYear());
        }
    }
}