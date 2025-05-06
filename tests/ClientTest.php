<?php

declare(strict_types=1);

use LiquidAgencyPtyLtd\PhpSalesforce\Client;
use PHPUnit\Framework\TestCase;
use Dotenv\Dotenv;

class ClientTest extends TestCase
{
    /** 
     * @var Client 
     */
    protected $client;

    /**
     * Sets up the test environment by creating a new Salesforce client instance.
     *
     * @return void
     */
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

    /**
     * Tests the query method of the Salesforce client.
     * 
     * Verifies both the structure and content of the returned data:
     * 
     * @return void
     */
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

    /**
     * Ensures an invalid SOQL query will throw an exception.
     * 
     * @return void
     */
    public function testInvalidQuery(): void
    {
        $this->expectException(\Exception::class);
        $this->client->query("SELECT Id, Name FROM InvalidObject");
    }
}
