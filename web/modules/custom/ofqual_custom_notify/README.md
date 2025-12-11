# Ofqual Custom Notify

## Introduction

The Ofqual Custom Notify module allows Drupal to send notifications
to the Ofqual Notifications API.

This module reads configuration values such as API endpoint and client
credentials from a JSON key file stored on the server. It then sends
notification payloads to the configured API whenever a user login event
is triggered.

Secrets are never stored in configuration or the database.

## Requirements

* Drupal 11 core
* PHP 8.2 or higher
* Key module (enabled)
* Guzzle HTTP client (included with Drupal core)

No additional modules or libraries are required.

## Installation

Install Ofqual Custom Notify using the standard Drupal installation method.

1. Place the module in your site's `modules/custom/` directory.
2. Enable dependencies and this module:
```
 drush en key ofqual_custom_notify -y
 drush cr
 ```

## Configuration

### Key file

The module reads credentials and endpoint details from a JSON file,
which should be located outside of the webroot for security.

Example path:

/var/www/html/keys/ofqual_api_credentials.json

The JSON file must define these fields:

```json
{
  "client_id": "<CLIENT_ID>",
  "client_secret": "<CLIENT_SECRET>",
  "grant_type": "client_credentials",
  "scope": "api://<APP_ID>/.default",
  "token_endpoint": "<TOKEN_URL>",
  "notificationapi": "<NOTIFICATION_API_URL>",
  "source_id": "<SOURCE_GUID>",
  "source_upn": "<SOURCE_UPN>",
  "target_upn": "<TARGET_UPN>"
}
```

### Settings

Add the following to your 'sites/default/settings.php' file:

```
$settings['ofqual_notify_json'] = '/var/www/html/keys/ofqual_api_credentials.json';
```

Do not commit this file to version control.
Add /keys/ to your .gitignore.

## Usage

* The module reads configuration from the specified JSON file.
* Sends POST requests with notification payloads to the API endpoint.
* Logs success and failure messages in Drupal’s log.

## Security

* Keep key files outside of webroot.
* Never log secrets or sensitive data.

## Maintainers

* Module: Ofqual Custom Notify (`ofqual_custom_notify`)
* Maintainer: Ofqual Drupal Team
* License: GNU General Public License v2 or later
