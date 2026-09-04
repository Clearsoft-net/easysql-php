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

Ask questions in natural language to your MySQL, MariaDB, or PostgreSQL databases directly from your PHP applications and Laravel projects.

## Requirements

- **PHP** >= 8.2
- **ext-json**
- **guzzlehttp/guzzle** ^7.0
- *(Optional)* **Laravel** ^11.0 (for Service Provider and Facade)

## Installation

```bash
composer require clearsoft/easysql-sdk
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

use Clearsoft\EasySQL\SDK\Client;

$client = new Client([
    'base_url'     => 'https://api.easysql.net',
    'access_token' => 'your-access-token',
]);

// Authentication
$tokens = $client->login([
    'email'    => 'user@example.com',
    'password' => 'secret',
]);
$user = $client->me();

// Managing Database Connectors
$client->createConnector([
    'name'   => 'Production MySQL',
    'type'   => 'mysql',
    'config' => [
        'host'     => 'db.example.com',
        'port'     => 3306,
        'database' => 'myapp',
        'user'     => 'readonly',
        'password' => 'secret',
    ],
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

### Automatic Token Refresh (EasySQLClient)

For automatic token refresh on 401 Unauthorized responses and persistent token storage:

```php
use Clearsoft\EasySQL\SDK\EasySQLClient;
use Clearsoft\EasySQL\SDK\TokenStoreInterface;

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

---

## API Overview

| Module | Available Methods |
|---|---|
| **Auth** | `register`, `login`, `refresh`, `me`, `deleteMe`, `updateMe`, `changePassword` |
| **Queries** | `createQuery`, `listQueries`, `getQuery` |
| **Connectors** | `listConnectors`, `createConnector`, `testConnector`, `getConnector`, `updateConnector`, `deleteConnector`, `syncConnector` |
| **Billing** | `getPlan`, `checkout`, `portal` |
| **Dashboard** | `dashboardStats` |
| **Health** | `health`, `healthHealth` |

See [docs/API.md](docs/API.md) for full endpoint reference and parameters.

---

## Development & Contributing

Contributions are welcome! Please read our **[Contributing Guidelines](https://github.com/Clearsoft-net/easysql-php/blob/main/CONTRIBUTING.md)** for details on the development workflow and pull request process.

```bash
make install                          # install composer dependencies
make lint                             # check PHP syntax
make test                             # run PHPUnit test suite
make generate                         # regenerate Client & Models from OpenAPI spec
make build                            # full build (generate + lint + test)
```

---

## License

This project is open source and licensed under the [MIT License](./LICENSE).

Maintained by **[Clearsoft](https://clearsoft.net)**.
