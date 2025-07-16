<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CompanyTest extends ApiTestCase
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
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

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
        $user->setEmail('test-company-' . uniqid() . '@example.com')
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
    public function testCreateCompany(): void
    {
        $client = static::createClient();
        $client->request('POST', '/companies', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => [
                'name' => 'Test Company',
                'address' => '123 Business Street',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['name' => 'Test Company']);
    }

    public function testGetCompany(): void
    {
        $client = static::createClient();
        $client->request('GET', '/companies/' . $this->company->getId(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Test Company']);
    }

    public function testUpdateCompany(): void
    {
        $client = static::createClient();
        $client->request('PATCH', '/companies/' . $this->company->getId(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/merge-patch+json'
            ],
            'json' => [
                'address' => '456 New Address',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['address' => '456 New Address']);
    }

    // Create a new company specifically for deletion testing
    public function testDeleteCompany(): void
    {
        $client = static::createClient();

        // First create a new company that won't have any assessments
        $response = $client->request('POST', '/companies', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => [
                'name' => 'Company To Delete',
                'address' => '789 Deletion Street',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $companyId = $data['id'];

        // Now delete the newly created company
        $client->request('DELETE', '/companies/' . $companyId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseStatusCodeSame(204);
    }
}
