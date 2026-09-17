# easysql/connectors-postgres

PostgreSQL connector for the EasySQL PHP SDK — local schema introspection and SELECT execution.

## Installation

```bash
composer require easysql/connectors-postgres
```

Requirements: PHP >= 8.2, ext-pdo, ext-pdo_pgsql.

## Usage

```php
use Clearsoft\EasySQL\Connectors\Postgres\ConnectionConfig;
use Clearsoft\EasySQL\Connectors\Postgres\Connector;

$connector = new Connector(new ConnectionConfig(
    host: '127.0.0.1',   // default
    port: 5432,          // default
    user: 'postgres',    // default
    password: 'secret',  // in-memory only — never written to disk or logged
    database: 'myapp',
    sslmode: 'prefer',   // libpq sslmode: disable|allow|prefer|require|verify-ca|verify-full
    schemas: ['public'], // schemas to introspect
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
  Driver error messages are sanitized (connection URIs and `password=`/`pwd=` assignments) before surfacing.
- **Introspection** reads `pg_catalog` (`pg_class`/`pg_namespace`/`pg_attribute`/`pg_attrdef`/`pg_constraint`)
  for the configured schemas, excluding partitions; row counts come from `reltuples` (best effort).
- **Execution** accepts SELECT/WITH/EXPLAIN/SHOW only (client-side safety check mirrors the server);
  uses prepared statements and returns typed rows.
- **No global state** — several connections can be open at the same time.

## Type mapping

`format_type()` output is normalized by `TypeMapper`; modifiers and array suffixes are preserved.
PostgreSQL types without a JSON equivalent are kept under their canonical PostgreSQL name (no lossy
conversion) — the API stores the type string verbatim:

| PostgreSQL type | Schema vocabulary |
|---|---|
| `character varying(n)` | `varchar(n)` |
| `character(n)` | `char(n)` |
| `double precision` | `double` |
| `timestamp without time zone` | `timestamp` |
| `timestamp with time zone` | `timestamptz` |
| `time without time zone` | `time` |
| `time with time zone` | `timetz` |
| `bit varying(n)` | `varbit(n)` |
| `integer[]`, `varchar(50)[]`, … | array suffix preserved |
| `bytea`, `jsonb`, `uuid`, `tsvector`, `xml`, ranges, enums, … | verbatim |

## Tests

```bash
./vendor/bin/phpunit --testsuite connectors-postgres
EASYSQL_TEST_POSTGRES=1 ./vendor/bin/phpunit --testsuite connectors-postgres  # + integration vs local PostgreSQL
```

Integration env vars: `EASYSQL_TEST_POSTGRES_HOST/PORT/USER/PASSWORD/DATABASE/SSLMODE`
(defaults `127.0.0.1:5433/postgres/postgres/easysql_test/prefer` — `make db-up` maps the
container to host port 5433 to avoid clashing with a local PostgreSQL on 5432).
