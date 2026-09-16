# clearsoft/easysql-laravel

Laravel integration for the EasySQL PHP SDK — service provider, connection manager and facade.

## Installation

```bash
composer require clearsoft/easysql-laravel
```

Requirements: PHP >= 8.2, Laravel ^11.0, `clearsoft/easysql-client`.

Package discovery is automatic (provider + `EasySQL` alias registered via `extra.laravel`).

## Usage

```bash
php artisan vendor:publish --tag=easysql-config
```

```env
EASYSQL_BASE_URL=https://api.easysql.net
EASYSQL_ACCESS_TOKEN=your-access-token
EASYSQL_TIMEOUT=30
```

```php
use Clearsoft\EasySql\Laravel\Facades\EasySQL;

$result = EasySQL::createQuery(['connector_id' => 'conn_abc123', 'question' => '...']);
$analytics = EasySQL::connection('analytics');
```

## Tests

```bash
./vendor/bin/phpunit --testsuite laravel
```
