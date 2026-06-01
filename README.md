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

### Basic client

```php
<?php

require 'vendor/autoload.php';

use Clearsoft\EasySQL\SDK\EasySQLClient;

$client = new EasySQLClient([
    'base_url'    => 'https://api.easysql.net',
    'access_token' => 'your-token-here',
]);

$response = $client->getHttpClient()->post('/v1/auth/login', [
    'json' => [
        'email'    => 'user@example.com',
        'password' => 'my-password',
    ],
]);

echo $response->getBody();
```

### Automatic token refresh

The client automatically retries failed requests on `401 Unauthorized` by calling the
`/v1/auth/refresh` endpoint with the stored refresh token. No extra code needed.

```php
$client = new EasySQLClient([
    'base_url'      => 'https://api.easysql.net',
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
]);
```

### Persistent token store

Implement `TokenStoreInterface` to persist tokens between requests (session, database, cache, etc.):

```php
<?php

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
```

Then attach it to the client:

```php
$client = new EasySQLClient(['base_url' => 'https://api.easysql.net']);
$client->setTokenStore(new SessionTokenStore());

// After a successful login:
$client->setTokens($accessToken, $refreshToken);

// Tokens are now automatically persisted and refreshed.
```

### Manual token management

```php
$client->setTokens($newAccessToken, $newRefreshToken);
$client->clearTokens(); // e.g., on logout
```

## Development

```bash
composer install
composer dump-autoload
```

## License

MIT
