# clearsoft/easysql-common

Shared code for the EasySQL PHP SDK packages.

## Installation

```bash
composer require clearsoft/easysql-common
```

Requirements: PHP >= 8.2.

## Contents

- `Clearsoft\EasySQL\Common\SqlValidator` — client-side SELECT-only safety check used by the
  MySQL, PostgreSQL and SQLite connectors before executing any generated SQL. Mirrors the
  server-side validator as defense in depth.
- `Clearsoft\EasySQL\Common\CredentialSanitizer` — strips credentials (connection URIs and
  `password=`/`pwd=` assignments) from driver messages before they are surfaced or logged.

## Usage

```php
use Clearsoft\EasySQL\Common\SqlValidator;
use Clearsoft\EasySQL\Common\CredentialSanitizer;

$result = SqlValidator::validateSelectOnly('SELECT * FROM users LIMIT 10');
if (!$result['ok']) {
    throw new RuntimeException($result['reason']);
}

echo CredentialSanitizer::sanitize('postgres://user:secret@host:5432/db');
// postgres://***@host:5432/db
```

## Tests

```bash
./vendor/bin/phpunit --testsuite common
```
