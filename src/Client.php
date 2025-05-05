<?php

namespace LiquidAgencyPtyLtd\PhpSalesforce;

abstract class Client
{
    protected $httpClient;

    protected $token;

    public function __construct()
    {
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri' => getenv('SALESFORCE_ENDPOINT')
        ]);
    }

    protected function authenticate()
    {
        if(!$this->token) {
            $this->credentialCheck();

            $parameters['form_params'] = [
                'grant_type' => 'password',
                'client_id' => getenv('SALESFORCE_CLIENT_ID'),
                'client_secret' => getenv('SALESFORCE_CLIENT_SECRET'),
                'username' => getenv('SALESFORCE_USERNAME'),
                'password' => getenv('SALESFORCE_PASSWORD') . getenv('SALESFORCE_TOKEN')
            ];

            $request = $this->httpClient->request('post', '/services/oauth2/token', $parameters);
            $response = json_decode($request->getBody(), true);

            if ($response) {
                if(!empty($response['error'])) {
                    throw new \Exception($response['error_description']);
                }

                $this->token = $response['access_token'];
            } else {
                throw new \Exception('PHP Salesforce | Unable to authenticate with Salesforce.');
            }
        }
    }

    public function create()
    {
        //
    }

    public function delete()
    {
        //
    }

    public function query()
    {
        //
    }

    public function upsert()
    {

    }

    private function credentialCheck()
    {
        if(!getenv('SALESFORCE_CLIENT_ID') || !getenv('SALESFORCE_CLIENT_SECRET') || !getenv('SALESFORCE_ENDPOINT') || !getenv('SALESFORCE_USERNAME') || !getenv('SALESFORCE_PASSWORD') || !getenv('SALESFORCE_TOKEN')) {
            throw new \Exception('PHP Salesforce | Missing Salesforce environment variables. Please set the following: [SALESFORCE_ENDPOINT, SALESFORCE_USERNAME, SALESFORCE_PASSWORD, SALESFORCE_TOKEN]');
        }
    }
}