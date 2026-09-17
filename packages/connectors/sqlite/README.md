# easysql/connectors-sqlite

SQLite connector for the EasySQL PHP SDK — local schema introspection and SELECT execution.

## Installation

```bash
composer require easysql/connectors-sqlite
```

Requirements: PHP >= 8.2, ext-pdo, ext-pdo_sqlite.

## Usage

```php
use Clearsoft\EasySQL\Connectors\SQLite\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\SQLite\Connector;

$connector = new Connector(new ConnectionConfig(
    path: '/var/data/shop.db', // or ':memory:' (default)
    readOnly: true,            // default false
    timeout: 10.0,             // seconds, default
));
$connector->connect();

try {
    $raw = $connector->introspect();          // raw shape for the schema-generation package
    $result = $connector->execute('SELECT * FROM users LIMIT 10');
} finally {
    $connector->close();
}
```

## Behavior

- **Introspection** reads `sqlite_master` (excludes `sqlite_%` internals) + `PRAGMA table_info` /
  `PRAGMA foreign_key_list` per table; row counts via `COUNT(*)` (best effort, `null` on failure).
- **Execution** accepts SELECT/WITH/EXPLAIN/SHOW only; prepared statements; typed rows.
- **Read-only mode** opens the database with `SQLITE_OPEN_READONLY` — writes are rejected by the driver.
- **No global state** — the database is opened per connection.

## Tests

```bash
./vendor/bin/phpunit --testsuite connectors-sqlite
```

Tests use a temp-file fixture database (created in `setUp`, removed in `tearDown`) plus `:memory:` cases —
no external service required.
