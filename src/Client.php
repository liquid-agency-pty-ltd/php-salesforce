<?php

namespace liquidagencyptyltd\phpsalesforce;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Exception;
use stdClass;

class Client
{
    /**
     * @var stdClass Configuration parameters
     */
    protected stdClass $config;

    /**
     * @var GuzzleClient|null HTTP client for API requests
     */
    protected ?GuzzleClient $httpClient;

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

    /***
     * Get the HTTP client for API requests
     *
     * @return GuzzleClient|null
     */
    protected function getHttpClient(): ?GuzzleClient
    {
        return $this->httpClient;
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

            $loginClient = new GuzzleClient([
                'base_uri' => $this->getLoginUrl(),
                'http_errors' => false
            ]);
            
            $response = $loginClient->request('POST', '/services/oauth2/token', $parameters);

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
            
            // Recreate the HTTP client with the instance URL for API calls
            $this->httpClient = new GuzzleClient([
                'base_uri' => $this->instanceUrl,
                'http_errors' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (GuzzleException $e) {
            throw new Exception('Failed to connect to Salesforce: ' . $e->getMessage());
        }
    }

    /**
     * Create a record in Salesforce
     *
     * @param string $sobject The Salesforce object (e.g., 'Account', 'Contact')
     * @param array $data The record data to create
     * @return array The created record response
     * @throws Exception If the operation fails
     */
    public function create(string $sobject, array $data): array
    {
        $this->connect();
        
        try {
            $response = $this->httpClient->request('POST', "/services/data/{$this->version}/sobjects/{$sobject}", [
                'json' => $data
            ]);
            
            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                throw new Exception("Failed to create {$sobject}: " . 
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }
            
            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to create {$sobject}: " . $e->getMessage());
        }
    }

    /**
     * Delete a record from Salesforce
     *
     * @param string $sobject The Salesforce object
     * @param string $recordId The ID of the record to delete
     * @return bool True if deletion was successful
     * @throws Exception If the operation fails
     */
    public function delete(string $sobject, string $recordId): bool
    {
        $this->connect();
        
        try {
            $response = $this->httpClient->request('DELETE', "/services/data/{$this->version}/sobjects/{$sobject}/{$recordId}");
            
            if ($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
                $result = json_decode($response->getBody(), true);

                throw new Exception("Failed to delete {$sobject}: " . 
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }
            
            return $response->getStatusCode() === 204;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to delete {$sobject}: " . $e->getMessage());
        }
    }

    /**
     * Get list of deleted records for an object in Salesforce
     * 
     * @param string $sobject The Salesforce object
     * @param string $start The start date/time in ISO8601 format (e.g. 2023-01-01T00:00:00+00:00)
     * @param string $end The end date/time in ISO8601 format (e.g. 2023-01-31T23:59:59+00:00)
     * @return array List of records
     * @throws Exception If the operation fails
     */
    public function getDeleted(string $sobject, string $start, string $end): array
    {
        $this->connect();

        try {
            $params = [
                'start' => $start,
                'end' => $end
            ];

            $response = $this->httpClient->request('GET', "/services/data/{$this->version}/sobjects/{$sobject}/deleted?" . http_build_query($params));

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                throw new Exception("Failed to list deleted {$sobject}: " .
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to list deleted {$sobject}: " . $e->getMessage());
        }
    }

    /**
     * Get list of updated records for an object in Salesforce
     * 
     * @param string $sobject The Salesforce object
     * @param string $start The start date/time in ISO8601 format (e.g. 2023-01-01T00:00:00+00:00)
     * @param string $end The end date/time in ISO8601 format (e.g. 2023-01-31T23:59:59+00:00)
     * @return array List of records
     * @throws Exception If the operation fails
     */
    public function getUpdated(string $sobject, string $start, string $end): array
    {
        $this->connect();

        try {
            $params = [
                'start' => $start,
                'end' => $end
            ];

            $response = $this->httpClient->request('GET', "/services/data/{$this->version}/sobjects/{$sobject}/updated?" . http_build_query($params));

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                throw new Exception("Failed to list updated {$sobject}: " .
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to list updated {$sobject}: " . $e->getMessage());
        }
    }

    /**
     * Retrieves a list of fields for an object in Salesforce.
     * 
     * @param string $sobject The Salesforce object
     * @return array List of fields
     * @throws Exception If the operation fails
     */
    public function listFields(string $sobject): array
    {
        $this->connect();

        try {
            $response = $this->httpClient->request('GET', "/services/data/{$this->version}/sobjects/{$sobject}/describe");

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                throw new Exception("Failed to list fields: " .
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to list fields: " . $e->getMessage());
        }
    }

    /**
     * Retrieves a list of all objects within Salesforce.
     *
     * @return array List of objects
     * @throws Exception If the operation fails
     */
    public function listObjects(): array
    {
        $this->connect();

        try {
            $response = $this->httpClient->request('GET', "/services/data/{$this->version}/sobjects");

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                throw new Exception("Failed to list objects: " .
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to list objects: " . $e->getMessage());
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
            $encodedQuery = urlencode($query);
            $response = $this->httpClient->request('GET', "/services/data/{$this->version}/query/?q={$encodedQuery}");
            
            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                throw new Exception("Query failed: " . 
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }
            
            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Query more records from Salesforce
     *
     * @param string $nextRecordsUrl The nextRecordsUrl from the previous query response
     * @return array Query results
     * @throws Exception If the query fails
     */
    public function queryMore(string $nextRecordsUrl): array
    {
        $this->connect();

        try {
            $response = $this->httpClient->request('GET', $nextRecordsUrl);

            $result = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 400) {
                throw new Exception("Query failed: " .
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Recursively uses query() and queryMore() to get all records for a query. No limit.
     *
     * @param string $query The SOQL query string
     * @return array Query results
     * @throws Exception If the query fails
     */
    public function queryAll(string $query): array
    {
        $this->connect();

        $results = [];
        $response = $this->query($query);

        $results = array_merge($results, $response['records']);

        while (isset($response['nextRecordsUrl'])) {
            $response = $this->queryMore($response['nextRecordsUrl']);
            $results = array_merge($results, $response['records']);
        }

        return $results;
    }

    /**
     * Upsert (update or insert) a record in Salesforce
     *
     * @param string $sobject The Salesforce object
     * @param string $externalIdField The external ID field name
     * @param string $externalId The external ID value
     * @param array $data The record data
     * @return array The upsert response
     * @throws Exception If the operation fails
     */
    public function upsert(string $sobject, string $externalIdField, string $externalId, array $data): array
    {
        $this->connect();
        
        try {
            $response = $this->httpClient->request(
                'PATCH', 
                "/services/data/{$this->version}/sobjects/{$sobject}/{$externalIdField}/{$externalId}", 
                ['json' => $data]
            );
            
            $result = json_decode($response->getBody(), true);
            
            if ($response->getStatusCode() >= 400) {
                throw new Exception("Failed to upsert {$sobject}: " . 
                    ($result[0]['message'] ?? 'Unknown error') . ' (' . $response->getStatusCode() . ')');
            }
            
            return $result;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to upsert {$sobject}: " . $e->getMessage());
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