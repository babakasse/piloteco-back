<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CarbonAssessmentTest extends ApiTestCase
{
    private ?string $token = null;
    private ?EntityManagerInterface $entityManager = null;
    private ?User $user = null;
    private ?Company $company = null;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class); // Add this line

        // Create test data
        $this->createTestData();
        // Get JWT token for authentication
        $this->token = $this->getToken($client);
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

        // Create a user with a unique email using a timestamp
        $user = new User();
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashedPassword);
        $user->setEmail('test-api-' . uniqid() . '@example.com')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setRoles(['ROLE_USER'])
            ->setCompany($company);
        $this->entityManager->persist($user);
        $this->user = $user;


        $this->entityManager->flush();
    }

    private function getToken($client): string
    {
        $response = $client->request('POST', '/login', [
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

    public function testGetCollection(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/assessment', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([]);
    }

    public function testCreateAssessment(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/assessment', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => [
                'name' => 'API Test Assessment',
                'description' => 'Created via API test',
                'assessmentDate' => (new \DateTime())->format('Y-m-d'),
                'status' => 'draft'
            ]
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([
            'name' => 'API Test Assessment',
            'description' => 'Created via API test',
            'status' => 'draft'
        ]);

        // Get the ID of the created assessment
        $data = json_decode($response->getContent(), true);
        $assessmentId = $data['id'];

        // Test getting the created assessment
        $response = $client->request('GET', '/assessment/' . $assessmentId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $assessmentId,
            'name' => 'API Test Assessment'
        ]);
    }

    public function testCreateAssessmentWithEmissions(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/assessment', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => [
                'name' => 'Assessment With Emissions',
                'description' => 'Assessment with emissions data',
                'assessmentDate' => (new \DateTime())->format('Y-m-d'),
                'status' => 'draft',
                'emissions' => [
                    [
                        'source' => 'Electricity',
                        'category' => 'Energy',
                        'activityData' => 1000,
                        'emissionFactor' => 0.5,
                        'scope' => 2,
                        'description' => 'Electricity consumption'
                    ],
                    [
                        'source' => 'Company Vehicles',
                        'category' => 'Transportation',
                        'activityData' => 500,
                        'emissionFactor' => 2.3,
                        'scope' => 1,
                        'description' => 'Fuel consumption'
                    ]
                ]
            ]
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([
            'name' => 'Assessment With Emissions',
            'description' => 'Assessment with emissions data'
        ]);

        // Get the ID of the created assessment
        $data = json_decode($response->getContent(), true);
        $assessmentId = $data['id'];

        // Test getting the emissions for the created assessment
        $response = $client->request('GET', '/assessment/' . $assessmentId . '/emissions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJsonContains([]);
    }

    public function testGetAssessmentSummary(): void
    {
        // First create an assessment
        $client = static::createClient();
        $response = $client->request('POST', '/assessment', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => [
                'name' => 'Summary Test Assessment',
                'description' => 'Assessment for summary test',
                'assessmentDate' => (new \DateTime())->format('Y-m-d'),
                'status' => 'published'
            ]
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($response->getContent(), true);
        $assessmentId = $data['id'];

        // Test getting the summary
        $response = $client->request('GET', '/assessment/' . $assessmentId . '/summary', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($assessmentId, $data['assessment']['id']);
        $this->assertEquals('Summary Test Assessment', $data['assessment']['name']);
        $this->assertEquals('published', $data['assessment']['status']);
        $this->assertArrayHasKey('emissionsCount', $data);
        $this->assertArrayHasKey('totals', $data);
        $this->assertArrayHasKey('byScope', $data);
        $this->assertArrayHasKey('byCategory', $data);
    }

    public function testUnauthorizedAccess(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/assessment', [
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
