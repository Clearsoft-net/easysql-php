# easysql/connectors-clickhouse

ClickHouse connector for the EasySQL PHP SDK — local schema introspection and SELECT execution over
the ClickHouse HTTP interface.

## Installation

```bash
composer require easysql/connectors-clickhouse
```

Requirements: PHP >= 8.2, ext-curl.

## Usage

```php
use Clearsoft\EasySQL\Connectors\ClickHouse\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\ClickHouse\Connector;

$connector = new Connector(new ConnectionConfig(
    host: '127.0.0.1',   // default
    port: null,          // default: 8443 when ssl, else 8123
    user: 'default',     // default
    password: 'secret',  // in-memory only — never written to disk or logged
    database: 'default', // default
    ssl: false,          // default — uses HTTPS and port 8443 when true
    timeout: 10.0,       // seconds, default
));
$connector->connect();

try {
    $raw = $connector->introspect();          // raw shape for the schema-generation package
    $result = $connector->execute('SELECT * FROM events LIMIT 10');
    // ['columns' => [...], 'rows' => [...], 'row_count' => N, 'duration_ms' => M]
} finally {
    $connector->close();
}
```

## Behavior

- **Credentials** are supplied per connection and never written to disk, logged, or kept after `close()`.
  Driver error messages are sanitized before surfacing.
- **Introspection** reads `system.tables` / `system.columns` (skipping views) and produces the raw shape
  consumed by `easysql/schema-generation`, including the engine-native type (e.g. `Nullable(Decimal(18, 4))`).
- **Execution** accepts SELECT/WITH/EXPLAIN/SHOW only (client-side safety check mirrors the server);
  queries run against the HTTP interface and return typed rows.
- **No global state** — several connections can be open at the same time.

## Tests

```bash
./vendor/bin/phpunit --testsuite connectors-clickhouse
EASYSQL_TEST_CLICKHOUSE=1 ./vendor/bin/phpunit --testsuite connectors-clickhouse  # + integration vs local ClickHouse
```

Integration env vars: `EASYSQL_TEST_CLICKHOUSE_HOST/PORT/USER/PASSWORD/DATABASE` (defaults `127.0.0.1:8123/default//default`).
