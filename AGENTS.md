# AGENTS.md

## Project overview

This is the official **PHP SDK** for the EasySQL API — a **multipackage Composer repository**.
Only the `client` package is **auto-generated** from the OpenAPI specification at
`https://api.easysql.net/openapi.json`. Manual edits to generated files will be overwritten
(CI fails via `make check`).

## Architecture

```
packages/
├── client/                         # 🤖 Generated Client + Models + docs; ✋ minimal runtime
│   ├── src/
│   │   ├── Client.php              # 🤖 Generated — named API methods
│   │   ├── Models/                 # 🤖 Generated — request/response DTOs
│   │   ├── Exceptions/             # ✋ ApiException (required by Client, never overwritten)
│   │   └── Http/                   # ✋ EasySQLClient + TokenStoreInterface (auto-refresh)
│   ├── docs/API.md                 # 🤖 Generated — API reference grouped by category
│   └── tests/                      # PHPUnit with Guzzle MockHandler
├── connectors/                     # ✋ Single package — factory + shared lifecycle
│   ├── src/                        #   ConnectorFactory, BaseConnector, ConnectorInterface,
│   │                               #   ConnectorEngine, ConnectionConfigInterface, ConnectorException, Helpers
│   ├── src/{MySQL,Postgres,SQLite,ClickHouse,SqlServer,Oracle,Firebird,Db2,DuckDb,LibSql,D1,Snowflake,BigQuery,Trino,Hana}/  # ✋ per-engine Connector, ConnectionConfig, Queries
│   └── tests/                      # PHPUnit (unit + env-gated integration)
├── common/                         # ✋ SqlValidator + CredentialSanitizer + ConnectionUrl (shared)
├── schema-generation/              # ✋ Raw introspection → canonical API schema payload (no I/O)
│   ├── src/TypeMapper.php          # ✋ Engine-native type → canonical schema vocabulary
│   └── tests/fixtures/             # Shared fixtures (also used by the TS sibling package)
└── laravel/                        # ✋ Service provider, manager, facade, config

scripts/
├── generate.php          # Orchestration — downloads spec, drives generation into packages/client
├── client.php.tpl        # Template for Client.php with {{IMPLEMENTATION}} placeholder
├── update-version.php    # semantic-release prepare — syncs version into all composer.json
└── lib/
    ├── extract.php       # Spec parsing → GeneratedMethod[] (merges path-item + op params)
    ├── build.php         # Code generation → method bodies + models (prunes stale models)
    └── docs.php          # Markdown docs generation → packages/client/docs/API.md

.github/workflows/
├── generate-sdk.yml      # CI — triggered by repository_dispatch from easysql-api
├── release.yml           # lint + composer validate + make check + test + semantic-release
└── split-packages.yml    # mirror each package to Clearsoft-net/easysql-php-* + forward tag (Packagist)
```

## Generation flow

1. `make generate` → `php scripts/generate.php`
2. Downloads `openapi.json` from `EASYSQL_API_URL` (env var or `--spec-url` flag)
3. `extractMethods(spec)` — walks every path/method → builds `GeneratedMethod[]`
4. `buildImpl(methods)` → renders full method bodies with docblocks
5. Injects into `client.php.tpl` → writes `packages/client/src/Client.php`
6. `generateModels(spec)` → writes `packages/client/src/Models/*.php` (deletes stale models)
7. `generateDocs(methods)` → writes `packages/client/docs/API.md`

## Key design decisions

### Operation ID → method name

Algorithm (from `extract.php:deriveMethodName`):
1. Strip trailing `_<httpMethod>` suffix (e.g., `_post`, `_get`)
2. Strip `_v\d+_<rest>` version marker
3. Convert remaining `snake_case` to `camelCase`

Examples:
- `login_v1_auth_login_post` → `login`
- `list_connections_v1_connections_get` → `listConnections`
- `get_connection_v1_connections__connection_id__get` → `getConnection`

### Parameter flattening

| Parameter types | Consumer writes |
|---|---|
| Body only | `$client->refresh(['refresh_token' => ...])` |
| Path only | `$client->getConnection('conn_123')` |
| Query only | `$client->listQueries(['page' => 1])` |
| Body + Path | `$client->updateConnection(['name' => 'X'], 'conn_123')` |

### Template placeholders

`{{IMPLEMENTATION}}` in `client.php.tpl` is replaced with the full generated method blocks (docblock + signature + body).

### Namespaces

| Package | Namespace |
|---|---|
| client | `Clearsoft\EasySQL\Client` (+ `\Models`, `\Exceptions`, `\Http`) |
| connectors | `Clearsoft\EasySQL\Connectors` (+ `\MySQL`, `\Postgres`, `\SQLite`, `\ClickHouse`) |
| common | `Clearsoft\EasySQL\Common` |
| schema-generation | `Clearsoft\EasySQL\SchemaGeneration` |
| laravel | `Clearsoft\EasySql\Laravel` (unchanged) |

### Connector → schema contract

Every connector's `introspect()` returns the shared raw shape consumed by `schema-generation`
(mirrors the TS sibling package): `['engine' => ..., 'tables' => [['name', 'columns' => [...],
'rowsApprox']]]`, where each column is `['name', 'dataType', 'nullable', 'primaryKey',
'defaultValue', 'foreignKey', 'ordinal']` (`dataType` is the engine-native type, verbatim).
`schema-generation` maps those types into the canonical vocabulary and emits the API payload.

### Connector architecture

`packages/connectors` is one package (`clearsoft/easysql-connectors`) mirroring the TS
`@easysql/connectors`: a single entry point plus a shared lifecycle. `BaseConnector` owns the
idempotent `connect`/`close`, the `execute` template (SELECT-only check, timing, result assembly)
and credential redaction; each engine implements only `openHandle`/`closeHandle`/`runQuery` and
its `introspect`, declares its catalog SQL in `Queries`, and is selected through
`ConnectorFactory::create($engine, $connection)`, which accepts an engine `ConnectionConfig`, an
options array, or an engine-native URL/DSN (parsed by `common`'s `ConnectionUrl` and mapped by each
config's `fromUrl`). Implemented engines: MySQL/MariaDB, PostgreSQL, SQLite, ClickHouse, SQL Server,
Oracle, Firebird, Db2, DuckDB, libSQL, Cloudflare D1, Snowflake, BigQuery, Trino and SAP HANA.
Engine drivers are optional PHP extensions declared under `suggest` — `ext-pdo_mysql`,
`ext-pdo_pgsql`, `ext-pdo_sqlite`, `ext-pdo_sqlsrv`, `ext-pdo_oci`, `ext-pdo_firebird`,
`ext-pdo_ibm`, `ext-pdo_duckdb`, `ext-pdo_odbc` (Snowflake/HANA), `ext-curl`
(ClickHouse/Trino/D1/remote libSQL/Neon HTTP) — plus the optional `google/cloud-bigquery` package, rather
than required dependencies, since Composer has no optional-per-engine require. The PostgreSQL
engine takes a `provider` (`postgres`/`neon`/`cockroachdb`/`redshift`, selecting provider-safe
catalog queries) and a `transport` (`tcp` via PDO, or `http` via Neon's HTTP SQL endpoint;
`websocket` is unsupported in PHP). Only the
integration-tested engines (MySQL, PostgreSQL, ClickHouse, SQLite) are exercised end-to-end in
CI; the rest are unit-tested and their catalog queries are unverified until run against a live
server (mirroring the TS package's caveat).

### Release

All packages are versioned and released together: `scripts/update-version.php` (via
`.releaserc.json` prepareCmd) writes the release version into the root and every
`packages/*/composer.json`. Root `composer.json` is the meta-package (`clearsoft/easysql-sdk`):
it `require`s client + schema-generation and lists connectors/Laravel under `suggest` +
`require-dev`, resolving every package via `repositories: path`.

Sub-packages are published on Packagist as `clearsoft/easysql-<name>` (client, common, laravel,
schema-generation, connectors). The `easysql` vendor is owned by an unrelated project on
Packagist, so `clearsoft` is used. `split-packages.yml` mirrors each `packages/*` into a
`Clearsoft-net/easysql-php-*` repo and forwards the release tag, which Packagist reads via its
webhook. It pushes with the `SPLIT_PAT` secret (the default `GITHUB_TOKEN` cannot push to the
sibling repos) and is dispatched by the release job, because a tag pushed with `GITHUB_TOKEN`
does not fire the `push: tags` event.

## Commands

```bash
make generate   # Re-generate packages/client from spec
make lint       # PHP syntax check (packages + scripts)
make analyse    # PHPStan static analysis (level 5, packages/)
make test       # PHPUnit (all packages; per-suite: --testsuite client|common|connectors|schema-generation|laravel)
make check      # Regenerate + fail if packages/client has any change (modified or untracked)
make build      # generate + lint + analyse + test
make db-up      # Start local MySQL + PostgreSQL for integration tests (Docker Compose)
make db-down    # Stop/remove the MySQL container
make test-integration  # Full suite with EASYSQL_TEST_MYSQL=1 (needs db-up)
make clean      # Remove generated files
```

Composer scripts mirror: `composer test`, `composer analyse`, `composer generate`.

## CI

- **generate-sdk**: trigger `repository_dispatch` (from `easysql-api` spec update) or `workflow_dispatch`
  → checkout → setup PHP → `make install` → `make generate` → detect changes → open PR
- **release**: push to main/master → `make install` → `make lint` → composer validate (root + packages)
  → `make check` → `make test` → semantic-release
- **split-packages**: on a `v*` tag or dispatched by the release job → mirror each `packages/*` to
  `Clearsoft-net/easysql-php-*` and forward the tag (Packagist webhook publishes it)
