<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Clearsoft-net/easysql-brand/main/logo/01-dark-horizontal.svg">
    <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/Clearsoft-net/easysql-brand/main/logo/02-light-horizontal.svg">
    <img alt="EasySQL Logo" src="https://raw.githubusercontent.com/Clearsoft-net/easysql-brand/main/logo/01-dark-horizontal.svg">
  </picture>
</p>

<h1 align="center">EasySQL PHP & Laravel SDK</h1>

<p align="center">
  <strong>Official PHP & Laravel SDK for the <a href="https://easysql.net">EasySQL API</a> · A <a href="https://clearsoft.net">Clearsoft</a> Product</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/clearsoft/easysql-sdk"><img src="https://img.shields.io/packagist/v/clearsoft/easysql-sdk?color=F97316&style=flat-square" alt="Packagist Version"></a>
  <a href="https://github.com/Clearsoft-net/easysql-php/actions"><img src="https://img.shields.io/github/actions/workflow/status/Clearsoft-net/easysql-php/release.yml?branch=main&style=flat-square" alt="CI Status"></a>
  <a href="https://packagist.org/packages/clearsoft/easysql-sdk"><img src="https://img.shields.io/badge/php-%3E%3D8.2-777BB4?style=flat-square" alt="PHP Version"></a>
  <a href="https://packagist.org/packages/clearsoft/easysql-sdk"><img src="https://img.shields.io/badge/laravel-%5E11.0-FF2D20?style=flat-square" alt="Laravel Version"></a>
  <a href="https://github.com/Clearsoft-net/easysql-php/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square" alt="License"></a>
  <a href="https://easysql.net"><img src="https://img.shields.io/badge/Product-easysql.net-F97316?style=flat-square" alt="Website"></a>
  <a href="https://clearsoft.net"><img src="https://img.shields.io/badge/Company-clearsoft.net-0F2B3D?style=flat-square" alt="Company"></a>
</p>

---

Ask questions in natural language to your MySQL, MariaDB, or SQLite databases directly from your PHP applications and Laravel projects.

## Packages

This is a multipackage repository. All packages are versioned and released together.

| Package | Composer name | Description |
|---|---|---|
| [`packages/client`](packages/client) | `clearsoft/easysql-client` | 🤖 Generated API client (from the OpenAPI spec) + token-refresh runtime |
| [`packages/connectors/mysql`](packages/connectors/mysql) | `clearsoft/easysql-connectors-mysql` | Local MySQL/MariaDB introspection + SELECT execution |
| [`packages/connectors/postgres`](packages/connectors/postgres) | `clearsoft/easysql-connectors-postgres` | Local PostgreSQL introspection + SELECT execution |
| [`packages/connectors/sqlite`](packages/connectors/sqlite) | `clearsoft/easysql-connectors-sqlite` | Local SQLite introspection + SELECT execution |
| [`packages/common`](packages/common) | `clearsoft/easysql-common` | Shared code (SQL safety validation, credential sanitization) |
| [`packages/schema-generation`](packages/schema-generation) | `clearsoft/easysql-schema-generation` | Raw introspection → API schema payload (deterministic, no I/O) |
| [`packages/laravel`](packages/laravel) | `clearsoft/easysql-laravel` | Laravel service provider, manager and facade |

## Requirements

- **PHP** >= 8.2
- **ext-json**
- **guzzlehttp/guzzle** ^7.0 (client package)
- **ext-pdo_mysql** (mysql connector), **ext-pdo_pgsql** (postgres connector), **ext-pdo_sqlite** (sqlite connector)
- *(Optional)* **Laravel** ^11.0 || ^12.0 (for Service Provider and Facade)

## Installation

```bash
# Client + schema generation (the meta-package; connectors and Laravel are opt-in)
composer require clearsoft/easysql-sdk

# Or pick individual packages:
composer require clearsoft/easysql-client
composer require clearsoft/easysql-connectors-mysql
composer require clearsoft/easysql-connectors-postgres
composer require clearsoft/easysql-connectors-sqlite
composer require clearsoft/easysql-schema-generation
composer require clearsoft/easysql-laravel
```
---

## Laravel Integration

The SDK provides first-class support for Laravel with automatic package discovery, configuration publishing, and a dedicated `EasySQL` facade.

### 1. Publish Configuration (Optional)

Publish the `easysql.php` configuration file:

```bash
php artisan vendor:publish --tag=easysql-config
```

This creates `config/easysql.php` in your Laravel application.

### 2. Configure Environment Variables

Add your credentials to your `.env` file:

```env
EASYSQL_BASE_URL=https://api.easysql.net
EASYSQL_ACCESS_TOKEN=your-access-token
EASYSQL_TIMEOUT=30
```

### 3. Using the Facade

```php
use Clearsoft\EasySql\Laravel\Facades\EasySQL;

// Ask questions in natural language
$result = EasySQL::createQuery([
    'connector_id' => 'conn_abc123',
    'question'     => 'How many users registered this month?',
]);

// Generated SQL and query results
$sql  = $result['sql'];
$rows = $result['result'];

// List recent queries
$queries = EasySQL::listQueries(['page' => 1, 'per_page' => 10]);

// Manage database connectors
$connectors = EasySQL::listConnectors();
$connector  = EasySQL::getConnector('conn_abc123');

// Access specific named connections defined in config/easysql.php
$analyticsClient = EasySQL::connection('analytics');
$stats = $analyticsClient->dashboardStats();
```

---

## Standalone PHP Usage

### Typed API Client (Recommended)

Use the generated `Client` with typed methods for all endpoints:

```php
<?php

require 'vendor/autoload.php';

use Clearsoft\EasySQL\Api\Client;

$client = new Client([
    'base_url'     => 'https://api.easysql.net',
    'access_token' => 'your-access-token',
]);

// Authentication
$tokens = $client->refresh(['refresh_token' => $refreshToken]);
$user = $client->me();

// Managing Database Connectors
$client->createConnector([
    'name'   => 'Production MySQL',
    'type'   => 'mysql',
    'schema' => $schemaPayload, // from the schema-generation package
]);

$connectors = $client->listConnectors();

// Running Natural Language Queries
$query = $client->createQuery([
    'connector_id' => 'conn_abc123',
    'question'     => 'What were the top 5 selling products last week?',
]);

print_r($query['sql']);
print_r($query['result']);
```

### Automatic Token Refresh

The typed `Client` supports a token store and refreshes the access token automatically (once)
on a 401, persisting the new tokens:

```php
use Clearsoft\EasySQL\Client\Client;
use Clearsoft\EasySQL\Client\Http\TokenStoreInterface;

$client = new Client([
    'base_url'      => 'https://api.easysql.net',
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
]);
$client->setTokenStore(new SessionTokenStore()); // implements TokenStoreInterface
```

For raw Guzzle access, `EasySQLClient` wraps the same token manager:

```php
use Clearsoft\EasySQL\Client\Http\EasySQLClient;
use Clearsoft\EasySQL\Client\Http\TokenStoreInterface;

$client = new EasySQLClient([
    'base_url'      => 'https://api.easysql.net',
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
]);

// Implement TokenStoreInterface to persist tokens in Redis, Session, or Database
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

### Typed DTOs

The generated `packages/client/src/Models` classes hydrate an API response array into a typed object:

```php
use Clearsoft\EasySQL\Client\Models\TokenResponse;

$tokens = TokenResponse::fromArray($client->refresh(['refresh_token' => $refreshToken]));
echo $tokens->access_token;
```

### Local Connectors + Schema Generation

Introspect a local database and push only the schema to the API — credentials never leave the machine:

```php
use Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\MySQL\Connector as MySQLConnector;
use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$connector = new MySQLConnector(new ConnectionConfig(
    host: '127.0.0.1',
    user: 'readonly',
    password: 'secret',
    database: 'myapp',
));
$connector->connect();

try {
    $raw = $connector->introspect();
    $payload = (new SchemaGenerator())->generate($raw);

    // Execute a generated SELECT locally
    $result = $connector->execute('SELECT * FROM users LIMIT 10');
} finally {
    $connector->close();
}

// Push the schema to the API
$client->syncConnector(['schema' => $payload], 'conn_abc123');
```

---

## API Overview

| Module | Available Methods |
|---|---|
| **Auth** | `refresh`, `logout`, `me`, `deleteMe`, `updateMe` |
| **Queries** | `createQuery`, `listQueries`, `getQuery`, `answerQuery` |
| **Connectors** | `listConnectors`, `createConnector`, `getConnector`, `getConnectorSchema`, `getSuggestions`, `updateConnector`, `deleteConnector`, `syncConnector` |
| **Billing** | `getPlan`, `checkout`, `portal` |
| **Dashboard** | `dashboardStats` |
| **Health** | `health`, `healthHealth` |

See [packages/client/docs/API.md](packages/client/docs/API.md) for full endpoint reference and parameters.

---

## Migration from the single-package layout (v1.x)

Version 1.x shipped a single package `clearsoft/easysql-sdk` with namespace `Clearsoft\EasySQL\SDK`.
The multipackage layout keeps that package as a meta-package (same name, same version line), so
`composer require clearsoft/easysql-sdk` keeps working — but the namespaces changed:

| Before (v1.x) | After |
|---|---|
| `Clearsoft\EasySQL\SDK\Client` | `Clearsoft\EasySQL\Api\Client` |
| `Clearsoft\EasySQL\SDK\Models\*` | `Clearsoft\EasySQL\Api\Models\*` |
| `Clearsoft\EasySQL\SDK\Exceptions\ApiException` | `Clearsoft\EasySQL\Api\Exceptions\ApiException` |
| `Clearsoft\EasySQL\SDK\EasySQLClient` | `Clearsoft\EasySQL\Api\Http\EasySQLClient` |
| `Clearsoft\EasySQL\SDK\TokenStoreInterface` | `Clearsoft\EasySQL\Api\Http\TokenStoreInterface` |
| `Clearsoft\EasySql\Laravel\*` | unchanged (now in `clearsoft/easysql-laravel`) |

Connector methods now take their path parameters explicitly (this also fixes a v1.x bug where
`getConnector('conn_1')` silently ignored the id and requested a literal `{connector_id}` URL):

```php
$client->getConnector('conn_abc123');              // was: getConnector() — broken URL
$client->updateConnector(['name' => 'X'], 'conn_abc123');
$client->syncConnector(['schema' => $payload], 'conn_abc123');
```

---

## Samples

Runnable examples for every package live in [`samples/`](samples) — from a basic
client call and token refresh to MySQL/PostgreSQL/SQLite introspection + sync and
the Laravel integration. See the [samples README](samples/README.md).

```bash
php samples/09-schema-generation.php   # no credentials needed
```

---

## Development & Contributing

Contributions are welcome! Please read our **[Contributing Guidelines](https://github.com/Clearsoft-net/easysql-php/blob/main/CONTRIBUTING.md)** for details on the development workflow and pull request process.

```bash
make install                          # install composer dependencies
make lint                             # check PHP syntax in every package
make analyse                          # PHPStan static analysis
make test                             # run PHPUnit test suite (all packages)
make generate                         # regenerate packages/client from OpenAPI spec
make check                            # verify packages/client was not hand-edited
make db-up                            # start local MySQL for integration tests
make test-integration                 # tests including MySQL integration (needs db-up)
make db-down                          # stop and remove the MySQL container
make build                            # full build (generate + lint + analyse + test)
```

Composer scripts mirror the common ones: `composer test`, `composer analyse`, `composer generate`.

---

## License

This project is open source and licensed under the [MIT License](./LICENSE).

Maintained by **[Clearsoft](https://clearsoft.net)**.
