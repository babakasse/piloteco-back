<?php

namespace App\Tests\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class LoginControllerTest extends ApiTestCase
{
    public function testSuccessfulLogin(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $response = $client->request('POST', '/api/login', [
            'json' => [
                'email' => 'admin@example.com',
                'password' => 'password123',
            ],
        ]);

        $jsonResponse = $response->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $jsonResponse, "The response must contain a token.");
        $this->assertIsString($jsonResponse['token'], "The token must be a string.");
        $this->assertMatchesRegularExpression('/^[\w-]+\.[\w-]+\.[\w-]+$/', $jsonResponse['token'], "The token does not match the JWT format.");
    }

    public function testInvalidCredentials(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $response = $client->request('POST', '/api/login', [
            'json' => [
                'email' => 'user@example.com',
                'password' => 'wrongpassword',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401, "The response should be 401 Unauthorized for invalid credentials.");
        $this->assertJsonContains(['message' => 'Invalid credentials.']);
    }
}
