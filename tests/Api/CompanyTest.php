<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

// TODO - DELETE API FROM THE ROUTING - IMPLEMENT TOKEN MANAGEMENT FOR ALL TESTS
class CompanyTest extends ApiTestCase
{
    public function testCreateCompany(): void
    {
        $client = static::createClient();
        $client->request('POST', '/companies', [
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
        $client->request('GET', '/companies/1');

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['name' => 'Company 1']);
    }

    public function testUpdateCompany(): void
    {
        $client = static::createClient();
        $client->request('PATCH', '/companies/1', [
            'json' => [
                'address' => '456 New Address',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['address' => '456 New Address']);
    }

    // TODO - IMPROVE RELATION WITH USER TO ENABLE DELETION
    public function testDeleteCompany(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/companies/1');

        $this->assertResponseStatusCodeSame(204);
    }
}
