# EasySQL SDK — PHP

Official PHP SDK for the [EasySQL API](https://easysql.net).

> ⚠️ **Auto-generated code.** This repository is maintained by the
> [`generate-sdks.yml`](https://github.com/Clearsoft-net/easysql-api/actions/workflows/generate-sdks.yml)
> workflow from the main API repository. Manually opened pull requests will be closed.

## Installation

```bash
composer require easysql/sdk
```

## Usage

```php
<?php

require 'vendor/autoload.php';

use Clearsoft\EasySQL\SDK\Client;

$client = new Client([
    'base_url' => 'https://api.easysql.net',
    'access_token' => 'your-token-here',
]);

$response = $client->post('/v1/auth/login', [
    'json' => [
        'email' => 'user@example.com',
        'password' => 'my-password',
    ],
]);

echo $response->getBody();
```

## Development

```bash
composer install
composer dump-autoload
```

## License

MIT
