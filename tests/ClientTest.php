<?php

declare(strict_types=1);

use liquidagencyptyltd\phpsalesforce\Client;
use PHPUnit\Framework\TestCase;
use Dotenv\Dotenv;

class ClientTest extends TestCase
{
    /** 
     * @var Client 
     */
    protected $client;

     /** 
     * @var string id of the created record
     */
    protected static $created;

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
     * Tests the retrieval of all objects in Salesfoce.
     * 
     * @return void
     */
    public function testListObjects(): void
    {
        $result = $this->client->listObjects();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['sobjects']);
    }

    /**
     * Tests the retrieval of all objects in Salesfoce.
     * 
     * @return void
     */
    public function testListFields(): void
    {
        $result = $this->client->listFields('RecordType');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['fields']);
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

    /** 
     * Test creation of a record.
     *
     * @return void
     */
    public function testCreate(): void
    {
        $result = $this->client->create('Contact', [
            'FirstName' => 'John',
            'LastName' => 'Doe',
            'Email' => 'john.doe@example.com',
        ]);

        if(!empty($result) && !empty($result['id'])) {
            self::$created = $result['id'];
        }

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    /** 
     * Test deletion of a record.
     *
     * @return void
     */
    public function testDelete(): void
    {
        $result = $this->client->delete('Contact', self::$created);

        $this->assertTrue($result);
    }

    /**
     * Tests the retrieval of deleted records for a specific object
     * 
     * @return void
     */
    public function testGetDeleted(): void
    {
        $result = $this->client->getDeleted('Contact', date('c', strtotime('-5 days')), date('c'));

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['deletedRecords']);
    }

    /**
     * Tests the retrieval of updated records for a specific object
     * 
     * @return void
     */
    public function testGetUpdated(): void
    {
        $result = $this->client->getUpdated('Contact', date('c', strtotime('-5 days')), date('c'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ids', $result);
    }
}
