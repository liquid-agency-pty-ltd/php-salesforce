# PHP Salesforce

This package is in development and not ready for use.

Connect to a Salesforce org via REST API.
- Requires a configured 'Connected App' in Salesforce.

Environment variables required:
```
SALESFORCE_ENDPOINT=
SALESFORCE_CLIENT_ID=
SALESFORCE_CLIENT_SECRET=
SALESFORCE_USERNAME=
SALESFORCE_PASSWORD=
```
SALESFORCE_ENDPOINT should be:  
    - `https://login.salesforce.com` for production environments.  
    - `https://test.salesforce.com` for sandbox environments.  
    - Not sure if custom domains will work.