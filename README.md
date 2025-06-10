# PHP Salesforce

- PHP `^7.4 || ^8.0`

This package is in development and not ready for use.

Connect to a Salesforce org via REST API.
- Requires a configured 'Connected App' in Salesforce.
- Must have 'Manage user data via APIs (api)' OAuth Scope.
- Will ask for a callback, and you have to enter one, but as we are using User/Password/Token to authenticate as well, it is not needed.
  `EXAMPLE.COM/oauth/callback` is what I usually set it to.

### Installation.

Put this in your `composer.json`
```
"require": {
  "liquidagencyptyltd/php-salesforce": "^1.0",
},
"repositories": [
  {
    "type": "vcs",
    "url": "git@github.com:liquid-agency-pty-ltd/php-salesforce.git"
  }
]
```

`composer install`

### Usage.

```
$client = new \liquidagencyptyltd\phpsalesforce\Client([
    'client_id' => getenv('SALESFORCE_CLIENT_ID'), // Connected App client id
    'client_secret' => getenv('SALESFORCE_CLIENT_SECRET'), // Connected App client secret
    'environment' => getenv('SALESFORCE_ENVIRONMENT'), // 'Production' or 'Sandbox'
    'password' => getenv('SALESFORCE_PASSWORD'),
    'token' => getenv('SALESFORCE_TOKEN'), // Security token
    'username' => getenv('SALESFORCE_USERNAME'),
]);

$object = 'Contact';
$id = '18_CHARACTER_SF_ID';
$data = ['firstname' => 'John', 'lastname' => 'Citizen'];

// Fetch results for SOQL query. Limit ~2k rows.
$results = $client->query($soql_query);

// Fetch the next set of records using the 'nextRecordsUrl' given by the query() function. Limit ~2k rows.
$results = $client->queryMore($nextRecordsUrl);

// Recursively fetch results for SOQL query. No Limit.
$results = $client->queryAll($my_soql_query);

// Create a record.
$results = $client->create($object, $data);

// Upsert a record. Must specify an which field is an external id and what that id is.
$results = $client->upsert($object, $externalIdField, $externalId, $data);

// Delete a record
$results = $client->delete($object, $id);

// Get deleted records in a certain period. Date formats: ISO8601 format (e.g. 2023-01-01T00:00:00+00:00)
$results = $client->getDeleted($object, $start_date, $end_date);

// Get updated records in a certain period. Date formats: ISO8601 format (e.g. 2023-01-01T00:00:00+00:00)
$results = $client->getUpdated($object, $start_date, $end_date);

// Retreive a list of Salesforce objects.
$results = $client->listObjects($object);

// Retreive a list of fields for a Salesforce object.
$results = $client->listfields($object);

```

### Testing.
Requires .env file with all the fields in .example.env filled in.  
Note: will create and delete a generic Contact record in the chosen Salesforce org - I suggest using a sandbox.

Run:
`./vendor/bin/phpunit tests`

### Todo.
- Add expected results to the README.
- SOAP API.
- Authentication methods:
    - Client Credentials.
    - Web Server (needs working oauth2 callback).
- This library only handles a small subset of possible API operations - will add as needed.

