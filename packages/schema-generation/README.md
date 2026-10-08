# easysql/schema-generation

Schema generation for the EasySQL PHP SDK — transforms raw connector introspection output into the
schema payload the API consumes (`POST /v1/connections`, `POST /v1/connections/{id}/sync`).

## Installation

```bash
composer require easysql/schema-generation
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
