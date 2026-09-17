# easysql/schema-generation

Schema generation for the EasySQL PHP SDK — transforms raw connector introspection output into the
schema payload the API consumes (`POST /v1/connectors`, `POST /v1/connectors/{id}/sync`).

## Installation

```bash
composer require easysql/schema-generation
```

Requirements: PHP >= 8.2. No other dependencies — and no I/O of its own.

## Usage

```php
use Clearsoft\EasySQL\SchemaGeneration\SchemaGenerator;

$payload = (new SchemaGenerator())->generate($rawFromConnector);
$client->syncConnector(['schema' => $payload], 'conn_abc123');
```

Input shape (produced by every connector package):

```php
['tables' => [['name' => 'users', 'columns' => [[
    'name' => 'id', 'type' => 'int', 'nullable' => false,
    'primary_key' => true, 'default' => null,
    'foreign_key' => null, // or ['table' => ..., 'column' => ...]
]], 'rows_approx' => 42]]]
```

## Behavior

- Maps engine-native types into the schema vocabulary (lowercased; empty SQLite declarations → `blob`).
- Deterministic: tables sorted by name; same input → byte-identical output (`toJson()`).
- Validates the input shape (`InvalidArgumentException` on malformed input).
- Performs no network, filesystem or database access.

### Determinism contract (TS parity)

Tables are sorted with a **byte-wise (ASCII) comparison** (`strcmp`). The TypeScript
sibling package must use the same byte ordering — not `localeCompare()`. Columns keep the
introspection order and name ties are stable, so the same database yields the same payload
in both SDKs.

## Tests

```bash
./vendor/bin/phpunit --testsuite schema-generation
```

Fixtures under `tests/fixtures/{mysql,sqlite}/*.json` hold raw connector output + expected payload and are
shared with the TypeScript sibling package so both SDKs produce identical payloads for the same database.
