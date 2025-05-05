<?php

namespace LiquidAgencyPtyLtd\PhpSalesforce;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Exception;
use stdClass;

class Client
{
    /**
     * @var GuzzleClient HTTP client for API requests
     */
    protected GuzzleClient $httpClient;

    /**
     * @var stdClass Configuration parameters
     */
    protected stdClass $config;

    /**
     * @var string|null Salesforce access token
     */
    protected ?string $token = null;

    /**
     * @var string|null Salesforce instance URL
     */
    protected ?string $instanceUrl = null;

    /**
     * @var string version of the Salesforce API to use
     */
    protected string $version = 'v63.0';

    /**
     * Client constructor
     *
     * @param array $config Configuration parameters
     * @throws Exception If configuration is invalid
     */
    public function __construct(array $config)
    {
        $this->config = (object)$config;
        $this->validateConfig();
        
        $this->httpClient = new GuzzleClient([
            'base_uri' => $this->getLoginUrl(),
            'http_errors' => false
        ]);
    }

    /**
     * Get the appropriate Salesforce login URL based on environment
     *
     * @return string Login URL
     */
    protected function getLoginUrl(): string
    {
        return $this->config->environment === 'Production' 
            ? 'https://login.salesforce.com' 
            : 'https://test.salesforce.com';
    }

    /**
     * Authenticate with Salesforce and get access token
     *
     * @return void
     * @throws Exception If authentication fails
     */
    protected function connect(): void
    {
        if ($this->token) {
            return;
        }

        try {
            $parameters = [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => $this->config->client_id,
                    'client_secret' => $this->config->client_secret,
                    'username' => $this->config->username,
                    'password' => $this->config->password . $this->config->token
                ]
            ];

            $response = $this->httpClient->request('POST', '/services/oauth2/token', $parameters);
            $statusCode = $response->getStatusCode();
            $responseData = json_decode($response->getBody(), true);

            if ($statusCode !== 200 || !$responseData) {
                throw new Exception('Failed to connect to Salesforce: Invalid response');
            }

            if (!empty($responseData['error'])) {
                throw new Exception('Salesforce authentication error: ' . $responseData['error_description']);
            }

            $this->token = $responseData['access_token'];
            $this->instanceUrl = $responseData['instance_url'];
        } catch (GuzzleException $e) {
            throw new Exception('Failed to connect to Salesforce: ' . $e->getMessage());
        }
    }

    /**
     * Create a record in Salesforce
     *
     * @param string $objectType The Salesforce object type (e.g., 'Account', 'Contact')
     * @param array $data The record data to create
     * @return array The created record response
     * @throws Exception If the operation fails
     */
    public function create(string $objectType, array $data): array
    {
        $this->connect();
        
        try {
            $client = new GuzzleClient([
                'base_uri' => $this->instanceUrl,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $response = $client->request('POST', "/services/data/{$this->version}/sobjects/{$objectType}", [
                'json' => $data
            ]);
            
            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new Exception("Failed to create {$objectType}: " . $e->getMessage());
        }
    }

    /**
     * Delete a record from Salesforce
     *
     * @param string $objectType The Salesforce object type
     * @param string $recordId The ID of the record to delete
     * @return bool True if deletion was successful
     * @throws Exception If the operation fails
     */
    public function delete(string $objectType, string $recordId): bool
    {
        $this->connect();
        
        try {
            $client = new GuzzleClient([
                'base_uri' => $this->instanceUrl,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token
                ]
            ]);
            
            $response = $client->request('DELETE', "/services/data/{$this->version}/sobjects/{$objectType}/{$recordId}");
            
            return $response->getStatusCode() === 204;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to delete {$objectType}: " . $e->getMessage());
        }
    }

    /**
     * Execute a SOQL query
     *
     * @param string $query The SOQL query string
     * @return array Query results
     * @throws Exception If the query fails
     */
    public function query(string $query): array
    {
        $this->connect();
        
        try {
            $client = new GuzzleClient([
                'base_uri' => $this->instanceUrl,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token
                ]
            ]);
            
            $encodedQuery = urlencode($query);
            $response = $client->request('GET', "/services/data/{$this->version}/query/?q={$encodedQuery}");
            
            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Upsert (update or insert) a record in Salesforce
     *
     * @param string $objectType The Salesforce object type
     * @param string $externalIdField The external ID field name
     * @param string $externalId The external ID value
     * @param array $data The record data
     * @return array The upsert response
     * @throws Exception If the operation fails
     */
    public function upsert(string $objectType, string $externalIdField, string $externalId, array $data): array
    {
        $this->connect();
        
        try {
            $client = new GuzzleClient([
                'base_uri' => $this->instanceUrl,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $response = $client->request(
                'PATCH', 
                "/services/data/{$this->version}/sobjects/{$objectType}/{$externalIdField}/{$externalId}", 
                ['json' => $data]
            );
            
            return json_decode($response->getBody(), true) ?: ['success' => true];
        } catch (GuzzleException $e) {
            throw new Exception("Failed to upsert {$objectType}: " . $e->getMessage());
        }
    }

    /**
     * Validate the configuration parameters
     *
     * @return void
     * @throws Exception If configuration is invalid
     */
    private function validateConfig(): void
    {
        $requiredFields = ['client_secret', 'client_id', 'environment', 'username', 'password', 'token'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($this->config->$field)) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new Exception('PHP Salesforce | Missing necessary configuration variables: [' . 
                implode(', ', $missingFields) . ']');
        }
    }
}