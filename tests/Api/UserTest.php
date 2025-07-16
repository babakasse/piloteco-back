<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserTest extends ApiTestCase
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
        $user->setEmail('test-user-api-' . uniqid() . '@example.com')
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

    public function testCreateUser(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/users', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ],
            'json' => [
                'email' => 'testuser-' . uniqid() . '@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'plainPassword' => 'securepassword',
                'roles' => ['ROLE_USER'],
                'company' => '/companies/' . $this->company->getId()
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);
    }

    public function testGetUser(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/users/' . $this->user->getId(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'email' => $this->user->getEmail(),
            'firstName' => 'Test',
            'lastName' => 'User'
        ]);
    }
    public function testUpdateUser(): void
    {
        $client = static::createClient();
        $response = $client->request('PATCH', '/users/' . $this->user->getId(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/merge-patch+json'
            ],
            'json' => [
                'firstName' => 'UpdatedName',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['firstName' => 'UpdatedName']);
    }
    public function testDeleteUser(): void
    {
        $client = static::createClient();
        $response = $client->request('DELETE', '/users/' . $this->user->getId(), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);

        $this->assertResponseStatusCodeSame(204);
    }
}
