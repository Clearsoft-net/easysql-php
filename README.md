# EasySQL SDK — PHP

Official PHP SDK for the [EasySQL API](https://easysql.net).

> ⚠️ **Auto-generated code.** This repository is maintained by the
> [`generate-sdks.yml`](https://github.com/Clearsoft-net/easysql-api/actions/workflows/generate-sdks.yml)
> workflow from the main API repository. Manually opened pull requests will be closed.

## Requirements

- PHP >= 8.2
- [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) ^7.0

## Installation

```bash
composer require easysql/sdk
```

## Usage

### Named API methods (recommended)

The SDK provides a generated `Client` with typed methods for every endpoint:

```php
<?php

require 'vendor/autoload.php';

use Clearsoft\EasySQL\SDK\Client;

$client = new Client([
    'base_url'     => 'https://api.easysql.net',
    'access_token' => 'your-token-here',
]);

// Auth
$tokens = $client->login(['email' => 'user@example.com', 'password' => 'secret']);
$user   = $client->me();

// Connectors
$connectors = $client->listConnectors();
$connector  = $client->getConnector('conn_abc123');
$client->createConnector([
    'type' => 'mysql',
    'name' => 'Production DB',
    'config' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'user'     => 'root',
        'password' => 'secret',
        'database' => 'myapp',
    ],
]);

// Queries
$result = $client->createQuery([
    'connector_id' => 'conn_abc123',
    'question'     => 'How many users signed up last week?',
]);

$history = $client->listQueries(['page' => 1, 'per_page' => 10]);
$detail  = $client->getQuery('qry_xyz');
```

### Token management (EasySQLClient)

For automatic token refresh and persistent storage, use the `EasySQLClient` wrapper:

```php
use Clearsoft\EasySQL\SDK\EasySQLClient;

$client = new EasySQLClient([
    'base_url'      => 'https://api.easysql.net',
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
]);

// The client automatically refreshes tokens on 401 responses.
// Use getHttpClient() for raw Guzzle requests or the Client for named methods.
$response = $client->getHttpClient()->get('/v1/auth/me');
```

### Persistent token store

Implement `TokenStoreInterface` to persist tokens between requests:

```php
use Clearsoft\EasySQL\SDK\TokenStoreInterface;

class SessionTokenStore implements TokenStoreInterface
{
    public function load(): ?array
    {
        return $_SESSION['easysql_tokens'] ?? null;
    }

    public function save(string $accessToken, string $refreshToken): void
    {
        $_SESSION['easysql_tokens'] = [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    public function clear(): void
    {
        unset($_SESSION['easysql_tokens']);
    }
}

$client->setTokenStore(new SessionTokenStore());
```

## API Reference

See [docs/API.md](docs/API.md) for the full list of endpoints.

## Development

```bash
# Install dependencies
composer install

# Regenerate from spec
make generate

# Run tests
make test

# Full build (generate + lint + test)
make build
```

### Code generation

```
make generate
```

Reads the OpenAPI spec from `EASYSQL_API_URL` (defaults to localhost) and regenerates:

| Output | Description |
|---|---|
| `src/Client.php` | API client with named methods |
| `src/Models/` | Request/response DTOs |
| `docs/API.md` | Markdown API reference |

## License

MIT
