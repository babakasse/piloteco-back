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
                'email' => 'user@example.com',
                'password' => 'string',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['token' => true]);
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
        $this->assertJsonContains(['error' => 'Invalid credentials']);
    }
}
