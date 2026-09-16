# clearsoft/easysql-connectors-mysql

MySQL/MariaDB connector for the EasySQL PHP SDK — local schema introspection and SELECT execution.

## Installation

```bash
composer require clearsoft/easysql-connectors-mysql
```

Requirements: PHP >= 8.2, ext-pdo, ext-pdo_mysql.

## Usage

```php
use Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\MySQL\Connector;

$connector = new Connector(new ConnectionConfig(
    host: '127.0.0.1',   // default
    port: 3306,          // default
    user: 'readonly',
    password: 'secret',  // in-memory only — never written to disk or logged
    database: 'myapp',
    charset: 'utf8mb4',  // default
    ssl: false,          // default
    timeout: 10.0,       // seconds, default
));
$connector->connect();

try {
    $raw = $connector->introspect();          // raw shape for the schema-generation package
    $result = $connector->execute('SELECT * FROM users LIMIT 10', ['status' => 'active']);
    // ['columns' => [...], 'rows' => [...], 'row_count' => N, 'duration_ms' => M]
} finally {
    $connector->close();
}
```

## Behavior

- **Credentials** are supplied per connection and never written to disk, logged, or kept after `close()`.
  Driver error messages are sanitized before surfacing.
- **Introspection** reads `information_schema` (user tables only) and produces the raw shape consumed by
  `clearsoft/easysql-schema-generation`.
- **Execution** accepts SELECT/WITH/EXPLAIN/SHOW only (client-side safety check mirrors the server);
  uses prepared statements and returns typed rows.
- **No global state** — several connections can be open at the same time.

## Tests

```bash
./vendor/bin/phpunit --testsuite connectors-mysql
EASYSQL_TEST_MYSQL=1 ./vendor/bin/phpunit --testsuite connectors-mysql  # + integration vs local MySQL
```

Integration env vars: `EASYSQL_TEST_MYSQL_HOST/PORT/USER/PASSWORD/DATABASE` (defaults `127.0.0.1:3306/root//easysql_test`).
