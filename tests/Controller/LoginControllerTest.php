<?php

namespace App\Tests\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class LoginControllerTest extends ApiTestCase
{
    public function testSuccessfulLogin(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/login', [
            'json' => [
                'email' => 'admin@example.com',
                'password' => 'password123',
            ],
        ]);

        $jsonResponse = $response->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $jsonResponse);
    }

    public function testInvalidCredentials(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/login', [
            'json' => [
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['message' => 'Invalid credentials.']);
    }
}
