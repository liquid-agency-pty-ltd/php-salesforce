<?php

declare(strict_types=1);

use LiquidAgencyPtyLtd\PhpSalesforce\Client;
use PHPUnit\Framework\TestCase;
use Dotenv\Dotenv;

class ClientTest extends TestCase
{
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        $this->client = new Client([
            'client_id' => $_ENV['PHPSF_CLIENT_ID'],
            'client_secret' => $_ENV['PHPSF_CLIENT_SECRET'],
            'environment' =>  $_ENV['PHPSF_ENVIRONMENT'],
            'password' => $_ENV['PHPSF_PASSWORD'],
            'token' => $_ENV['PHPSF_TOKEN'],
            'username' => $_ENV['PHPSF_USERNAME'],
        ]);
    }

    public function testQuery(): void
    {
        $result = $this->client->query("SELECT Id, Name, Email FROM Contact");

        $this->assertIsArray($result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('done', $result);
        $this->assertArrayHasKey('totalSize', $result);
        $this->assertIsArray($result['records']);
        $this->assertIsBool($result['done']);
        $this->assertIsInt($result['totalSize']);
    }
}
