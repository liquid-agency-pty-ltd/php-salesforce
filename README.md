# PHP Salesforce

This package is in development and not ready for use.

Connect to a Salesforce org via REST API.
- Requires a configured 'Connected App' in Salesforce.

### Testing
Requires .env file with all the fields in .example.env filled in.
Note: will create and delete a generic Contact record in the chosen Salesforce org - I suggest using a sandbox.

Run:
`./vendor/bin/phpunit tests`
