<?php

namespace App\Tests\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AssessmentControllerTest extends ApiTestCase
{
    private ?string $token = null;
    private ?EntityManagerInterface $entityManager = null;
    private ?HttpClientInterface $client = null;
    private ?User $user = null;
    private ?Company $company = null;
    private ?CarbonAssessment $assessment = null;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class); // Add this line
        
        // Create test data
        $this->createTestData();
        // Get JWT token for authentication
        $this->token = $this->getToken();
    }

    private function createTestData(): void
    {
        // Create a company
        $company = new Company();
        $company->setName('Test Company')
            ->setAddress('123 Test Street')
            ->setSector('Technology');
        $this->entityManager->persist($company);
        $this->company = $company;

        // Create a user with a unique email
        $user = new User();
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashedPassword);
        $user->setEmail('test-controller-' . uniqid() . '@example.com')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setRoles(['ROLE_USER'])
            ->setCompany($company);
        $this->entityManager->persist($user);
        $this->user = $user;

        // Create an assessment
        $assessment = new CarbonAssessment();
        $assessment->setName('Test Assessment')
            ->setDescription('Test Description')
            ->setAssessmentDate(new \DateTime())
            ->setStatus('draft')
            ->setCompany($company);
        $this->entityManager->persist($assessment);
        $this->assessment = $assessment;

        $this->entityManager->flush();
    }

    private function getToken(): string
    {
        $response = $this->client->request('POST', '/login', [
            'json' => [
                'email' => $this->user->getEmail(),
                'password' => 'password123'
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);

        return $data['token'];
    }

    public function testGetAssessments(): void
    {
        $response = $this->client->request('GET', '/assessment', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([
            [
                'name' => 'Test Assessment',
                'description' => 'Test Description',
                'status' => 'draft'
            ]
        ]);
    }

    public function testGetAssessment(): void
    {
        $response = $this->client->request('GET', '/assessment/' . $this->assessment->getId(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([
            'name' => 'Test Assessment',
            'description' => 'Test Description',
            'status' => 'draft'
        ]);
    }

    public function testCreateAssessment(): void
    {
        $response = $this->client->request('POST', '/assessment', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => [
                'name' => 'New Assessment',
                'description' => 'New Description',
                'assessmentDate' => (new \DateTime())->format('Y-m-d'),
                'status' => 'draft'
            ]
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([
            'name' => 'New Assessment',
            'description' => 'New Description',
            'status' => 'draft'
        ]);
    }

    public function testGetAssessmentEmissions(): void
    {
        $response = $this->client->request('GET', '/assessment/' . $this->assessment->getId() . '/emissions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        // The assessment has no emissions yet, so we expect an empty array
        $this->assertJsonEquals([]);
    }

    public function testGetAssessmentSummary(): void
    {
        $response = $this->client->request('GET', '/assessment/' . $this->assessment->getId() . '/summary', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([
            'id' => $this->assessment->getId(),
            'name' => 'Test Assessment',
            'status' => 'draft',
            'company' => [
                'id' => $this->company->getId(),
                'name' => 'Test Company'
            ]
        ]);
    }

    public function testAccessDeniedForOtherCompanyAssessment(): void
    {
        // Create another company and assessment
        $otherCompany = new Company();
        $otherCompany->setName('Other Company')
            ->setAddress('456 Other Street')
            ->setSector('Finance');
        $this->entityManager->persist($otherCompany);

        $otherAssessment = new CarbonAssessment();
        $otherAssessment->setName('Other Assessment')
            ->setDescription('Other Description')
            ->setAssessmentDate(new \DateTime())
            ->setStatus('draft')
            ->setCompany($otherCompany);
        $this->entityManager->persist($otherAssessment);

        $this->entityManager->flush();

        // Try to access the assessment from another company
        $response = $this->client->request('GET', '/assessment/' . $otherAssessment->getId(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonContains([
            'error' => 'Access denied to this assessment'
        ]);
    }
}