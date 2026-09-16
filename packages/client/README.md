# clearsoft/easysql-client

Generated API client for the [EasySQL API](https://easysql.net) — the `api` package of the PHP SDK.

## Status

🤖 **Generated** — `src/Client.php`, `src/Models/` and `docs/API.md` are produced by
`scripts/generate.php` from the OpenAPI spec at `/openapi.json`. Do not edit them by hand;
regenerate with `make generate` from the repository root. CI (`make check`) fails when the
generated files differ from a fresh regeneration.

The hand-written runtime in `src/Exceptions/` and `src/Http/` (required by the generated
client) is never overwritten by the generator.

## Installation

```bash
composer require clearsoft/easysql-client
```

Requirements: PHP >= 8.2, ext-json, guzzlehttp/guzzle ^7.0.

## Usage

```php
use Clearsoft\EasySQL\Api\Client;

$client = new Client([
    'base_url'     => 'https://api.easysql.net',
    'access_token' => 'your-access-token',
]);

$user = $client->me();
$connector = $client->getConnector('conn_abc123');
```

With automatic token refresh:

```php
use Clearsoft\EasySQL\Api\Http\EasySQLClient;

$client = new EasySQLClient([
    'base_url'      => 'https://api.easysql.net',
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
]);
$client->setTokenStore(new MyTokenStore()); // implements Http\TokenStoreInterface
```

See [docs/API.md](docs/API.md) for the full endpoint reference.
