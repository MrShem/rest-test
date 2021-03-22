<?php

namespace App\Tests;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiAuthTest extends WebTestCase
{
    public function testEnsureAuthNeeded()
    {
        $client = static::createClient();

        $client->request('GET', '/api/tasks');

        self::assertEquals(401, $client->getResponse()->getStatusCode());
    }

    /**
     * @dataProvider authDataProvider
     */
    public function testAuth($key, $status): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/tasks', [], [], ['HTTP_X-AUTH-TOKEN' => $key]);

        self::assertEquals($status, $client->getResponse()->getStatusCode());
    }

    public function authDataProvider(): ?\Generator
    {
        yield "correct token" => [
            'key' => 'root_key',
            'status' => 200
        ];

        yield "incorrect token" => [
            'key' => 'badKey',
            'status' => 401
        ];
    }
}
