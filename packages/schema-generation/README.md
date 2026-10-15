# clearsoft/easysql-schema-generation

Schema generation for the EasySQL PHP SDK — transforms raw connector introspection output into the
schema payload the API consumes (`POST /v1/connections`, `POST /v1/connections/{id}/sync`).

## Installation

```bash
composer require clearsoft/easysql-schema-generation
```

Requirements: PHP >= 8.2. No other dependencies — and no I/O of its own.

## Usage

```php
use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$payload = (new SchemaGenerator())->generate($rawFromConnector);
$client->syncConnection(['schema' => $payload], 'conn_abc123');
```

Input shape (produced by every connector package):

```php
[
    'engine' => 'mysql',
    'tables' => [[
        'name' => 'users',
        'columns' => [[
            'name' => 'id',
            'dataType' => 'int',        // engine-native, verbatim from the driver
            'nullable' => false,
            'primaryKey' => true,
            'defaultValue' => null,     // string|null
            'foreignKey' => null,       // or ['table' => ..., 'column' => ...]
            'ordinal' => 1,             // declared position
        ]],
        'rowsApprox' => 42,
    ]],
]
```

## Behavior

- Maps engine-native types into the canonical schema vocabulary (`integer`, `bigint`, `smallint`,
  `decimal`, `float`, `boolean`, `string`, `text`, `binary`, `blob`, `date`, `time`, `datetime`,
  `timestamp`, `interval`, `json`, `enum`, `uuid`, `unknown`). Unmapped types fall back to `unknown`.
  - MySQL/MariaDB: mapped by name (`tinyint(1)` → `boolean`).
  - SQLite: declared type resolved by affinity first, then refined by named types (`BOOLEAN`,
    `DATETIME`, `DATE`, `JSON`, `UUID`).
  - PostgreSQL: `format_type()` variants collapse to a storage class; arrays map to `json`.
  - ClickHouse: `Nullable(...)` / `LowCardinality(...)` are unwrapped; composite types
    (`Array`, `Map`, `Tuple`, `Nested`) map to `json`.
  - SQL Server: `timestamp` is the rowversion binary type (not temporal); `uniqueidentifier` → `uuid`.
  - Oracle: `NUMBER(p)` precision is inspected (`p <= 4` → `integer`, wider → `bigint`, otherwise
    `decimal`); `DATE` carries a time component → `datetime`.
  - Firebird: `BLOB` is split by sub-type (sub-type 1 / text → `text`, else `binary`); `INT128` →
    `bigint`.
  - Db2: `GRAPHIC`/`VARGRAPHIC` → `string`, `DBCLOB` → `text`, `XML` → `text`.
  - DuckDB: PostgreSQL mapping as its base, plus unsigned/128-bit integers and the nested
    `STRUCT`/`MAP`/`LIST`/`UNION`/`T[]` types → `json`.
  - libSQL and Cloudflare D1: delegate to the SQLite affinity rules.
  - Snowflake: the `NUMBER` family (`INTEGER`/`BIGINT`/…) collapses to `decimal`; `TIMESTAMP_*` →
    `timestamp`; `VARIANT`/`OBJECT`/`ARRAY` → `json`.
  - BigQuery: `INT64` → `bigint`; `ARRAY`/`STRUCT`/`RECORD` (incl. `ARRAY<T>`/`STRUCT<…>`) → `json`.
  - Trino: `ARRAY`/`MAP`/`ROW` → `json`; `IPADDRESS`/`IPPREFIX` → `string`.
  - SAP HANA: `SECONDDATE` → `datetime`; `ARRAY(...)` → `json`; `ST_GEOMETRY` → `unknown`.
- Deterministic: tables sorted by name, columns kept in declared order (`ordinal`) with a name-order
  fallback; same input → byte-identical output (`toJson()`).
- Validates the input shape (`InvalidArgumentException` on malformed input).
- Performs no network, filesystem or database access.

### Determinism contract (TS parity)

Tables are sorted with a **byte-wise (ASCII) comparison** (`strcmp`). The TypeScript sibling package
uses the same byte ordering — not `localeCompare()`. Columns keep the declared order and name ties are
stable, so the same database yields the same payload in both SDKs.

## Tests

```bash
./vendor/bin/phpunit --testsuite schema-generation
```

Fixtures under `tests/fixtures/{mysql,sqlite,clickhouse}.json` are the same raw connector dumps used by
the TypeScript sibling package, so both SDKs produce identical payloads for the same database.
