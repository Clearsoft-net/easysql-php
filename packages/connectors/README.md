# clearsoft/easysql-connectors

Database connectors for the EasySQL PHP SDK — local schema introspection and
SELECT execution for MySQL/MariaDB, PostgreSQL, SQLite, ClickHouse, SQL Server,
Oracle, Firebird, Db2, DuckDB, libSQL, Cloudflare D1, Snowflake, BigQuery,
Trino and SAP HANA.

## Installation

```bash
composer require clearsoft/easysql-connectors
```

Requirements: PHP >= 8.2 and `ext-pdo`. Each engine's driver is an optional
extension (or, for BigQuery, an optional Composer package), declared under
`suggest` and loaded only when that engine is used:

| Engine | Driver |
|---|---|
| MySQL/MariaDB | `ext-pdo_mysql` |
| PostgreSQL | `ext-pdo_pgsql` |
| SQLite | `ext-pdo_sqlite` |
| ClickHouse | `ext-curl` |
| SQL Server | `ext-pdo_sqlsrv` |
| Oracle | `ext-pdo_oci` |
| Firebird | `ext-pdo_firebird` |
| Db2 | `ext-pdo_ibm` |
| DuckDB | `ext-pdo_duckdb` |
| libSQL (local file) | `ext-pdo_sqlite` |
| libSQL (remote) | `ext-curl` |
| Cloudflare D1 | `ext-curl` |
| Snowflake | `ext-pdo_odbc` |
| SAP HANA | `ext-pdo_odbc` |
| Trino | `ext-curl` |
| BigQuery | `google/cloud-bigquery` |

## Usage

Every engine shares one lifecycle (`connect`/`close`/`introspect`/`execute`)
and one entry point, `ConnectorFactory`:

```php
use Clearsoft\EasySQL\Connectors\ConnectorEngine;
use Clearsoft\EasySQL\Connectors\ConnectorFactory;
use Clearsoft\EasySQL\Connectors\MySQL\ConnectionConfig;

$connector = ConnectorFactory::create(
    ConnectorEngine::MySQL,
    new ConnectionConfig(
        host: '127.0.0.1',   // default
        port: 3306,          // default
        user: 'readonly',
        password: 'secret',  // in-memory only — never written to disk or logged
        database: 'myapp',
    ),
);

$connector->connect();

try {
    $raw = $connector->introspect();          // raw shape for the schema-generation package
    $result = $connector->execute('SELECT * FROM users LIMIT 10', ['status' => 'active']);
    // ['columns' => [...], 'rows' => [...], 'row_count' => N, 'duration_ms' => M]
} finally {
    $connector->close();
}
```

An options array is accepted in place of a config object:

```php
$connector = ConnectorFactory::create(ConnectorEngine::SQLite, [
    'path' => '/srv/myapp.db',
    'readOnly' => true,
]);
```

Or an engine-native URL/DSN string (parsed by `clearsoft/easysql-common`):

```php
$connector = ConnectorFactory::create(ConnectorEngine::MySQL, 'mysql://app:secret@db.internal:3306/shop');
$connector = ConnectorFactory::create(ConnectorEngine::SQLite, 'sqlite:///srv/myapp.db');
```

Engine classes stay available directly from their sub-namespace when the engine
is already known:

```php
use Clearsoft\EasySQL\Connectors\Postgres\Connector;
use Clearsoft\EasySQL\Connectors\Postgres\ConnectionConfig;

$connector = new Connector(new ConnectionConfig(host: 'db.internal', database: 'shop'));
```

## Behavior

- **Shared lifecycle** — `connect()`/`close()` are idempotent; `execute()` runs
  a SELECT-only safety check, times the query and assembles the result; driver
  errors are wrapped with credentials stripped.
- **Credentials** are supplied per connection and never written to disk, logged,
  or kept after `close()`.
- **Introspection** reads each engine's system catalog and produces the shared
  raw shape (`engine`, engine-native `dataType`, and `ordinal`) consumed by
  `clearsoft/easysql-schema-generation`.
- **Execution** accepts SELECT/WITH/EXPLAIN/SHOW only (client-side safety check
  mirrors the server); uses prepared statements and returns typed rows.
- **No global state** — several connections can be open at the same time.

## Engines

| Engine id | Config class | Catalog |
|---|---|---|
| `mysql`, `mariadb` | `MySQL\ConnectionConfig` | `information_schema` |
| `postgresql` | `Postgres\ConnectionConfig` | `pg_catalog` |
| `sqlite` | `SQLite\ConnectionConfig` | `sqlite_master` + `PRAGMA` |
| `clickhouse` | `ClickHouse\ConnectionConfig` | `system` over HTTP |
| `sqlserver` | `SqlServer\ConnectionConfig` | `sys.*` |
| `oracle` | `Oracle\ConnectionConfig` | `ALL_*` dictionary views |
| `firebird` | `Firebird\ConnectionConfig` | `RDB$` system tables |
| `db2` | `Db2\ConnectionConfig` | `SYSCAT` |
| `duckdb` | `DuckDb\ConnectionConfig` | `duckdb_tables()`/`duckdb_columns()` |
| `libsql` | `LibSql\ConnectionConfig` | `sqlite_master` + `PRAGMA` |
| `d1` | `D1\ConnectionConfig` | `sqlite_master` + `PRAGMA` over the D1 REST API |
| `snowflake` | `Snowflake\ConnectionConfig` | `INFORMATION_SCHEMA` |
| `bigquery` | `BigQuery\ConnectionConfig` | dataset/table metadata (SDK) |
| `trino` | `Trino\ConnectionConfig` | `information_schema` over HTTP |
| `hana` | `Hana\ConnectionConfig` | `SYS` catalog |

### PostgreSQL providers and transports

The `postgresql` engine serves every PostgreSQL-compatible server. Select the
`provider` to pick catalog queries that are safe for it, and the `transport` to
pick how the server is reached:

```php
use Clearsoft\EasySQL\Connectors\ConnectorEngine;
use Clearsoft\EasySQL\Connectors\ConnectorFactory;

// Redshift (PostgreSQL 8.0 based — relispartition is omitted), over TCP.
ConnectorFactory::create(ConnectorEngine::PostgreSQL, [
    'provider' => 'redshift',
    'host' => 'cluster.redshift.amazonaws.com',
    'user' => 'admin',
    'password' => 'secret',
    'database' => 'analytics',
]);

// Neon over its HTTP SQL endpoint (one-shot queries, no persistent connection).
ConnectorFactory::create(ConnectorEngine::PostgreSQL, [
    'provider' => 'neon',
    'transport' => 'http',
    'connectionString' => 'postgresql://user:pass@ep-x.us-east-2.aws.neon.tech/db',
]);
```

Providers: `postgres` (default), `neon`, `cockroachdb`, `redshift`. Transports:
`tcp` (default, PDO) and `http` (Neon's HTTP SQL endpoint via cURL). The
`websocket` transport is **not supported by the PHP SDK** (PHP has no
PostgreSQL-over-WebSocket client); selecting it raises an actionable error.

## Tests

```bash
./vendor/bin/phpunit --testsuite connectors
EASYSQL_TEST_MYSQL=1 EASYSQL_TEST_POSTGRES=1 ./vendor/bin/phpunit --testsuite connectors  # + integration
```

Integration env vars: `EASYSQL_TEST_MYSQL_HOST/PORT/USER/PASSWORD/DATABASE`
(defaults `127.0.0.1:3306/root//easysql_test`) and the matching
`EASYSQL_TEST_POSTGRES_*` (`127.0.0.1:5433/postgres/postgres/easysql_test`).
