# AGENTS.md

## Project overview

This is the official **PHP SDK** for the EasySQL API — a **multipackage Composer repository**.
Only the `api` package is **auto-generated** from the OpenAPI specification at
`https://api.easysql.net/openapi.json`. Manual edits to generated files will be overwritten
(CI fails via `make check`).

## Architecture

```
packages/
├── api/                            # 🤖 Generated Client + Models + docs; ✋ minimal runtime
│   ├── src/
│   │   ├── Client.php              # 🤖 Generated — named API methods
│   │   ├── Models/                 # 🤖 Generated — request/response DTOs
│   │   ├── Exceptions/             # ✋ ApiException (required by Client, never overwritten)
│   │   └── Http/                   # ✋ EasySQLClient + TokenStoreInterface (auto-refresh)
│   ├── docs/API.md                 # 🤖 Generated — API reference grouped by category
│   └── tests/                      # PHPUnit with Guzzle MockHandler
├── connectors/
│   ├── mysql/                      # ✋ PDO MySQL — Connector, ConnectionConfig
│   ├── postgres/                   # ✋ PDO PostgreSQL — Connector, ConnectionConfig, TypeMapper
│   └── sqlite/                     # ✋ PDO SQLite — Connector, ConnectionConfig
├── common/                         # ✋ SqlValidator + CredentialSanitizer (shared)
├── schema-generation/              # ✋ Raw introspection → API schema payload (no I/O)
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
└── release.yml           # lint + composer validate + make check + test + semantic-release
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
- `list_connectors_v1_connectors_get` → `listConnectors`
- `get_connector_v1_connectors__connector_id__get` → `getConnector`

### Parameter flattening

| Parameter types | Consumer writes |
|---|---|
| Body only | `$client->refresh(['refresh_token' => ...])` |
| Path only | `$client->getConnector('conn_123')` |
| Query only | `$client->listQueries(['page' => 1])` |
| Body + Path | `$client->updateConnector(['name' => 'X'], 'conn_123')` |

### Template placeholders

`{{IMPLEMENTATION}}` in `client.php.tpl` is replaced with the full generated method blocks (docblock + signature + body).

### Namespaces

| Package | Namespace |
|---|---|
| api | `Clearsoft\EasySQL\Api` (+ `\Models`, `\Exceptions`, `\Http`) |
| connectors/mysql | `Clearsoft\EasySQL\Connectors\MySQL` |
| connectors/postgres | `Clearsoft\EasySQL\Connectors\Postgres` |
| connectors/sqlite | `Clearsoft\EasySQL\Connectors\SQLite` |
| common | `Clearsoft\EasySQL\Common` |
| schema-generation | `Clearsoft\EasySQL\SchemaGeneration` |
| laravel | `Clearsoft\EasySql\Laravel` (unchanged) |

### Release

All packages are versioned and released together: `scripts/update-version.php` (via
`.releaserc.json` prepareCmd) writes the release version into the root and every
`packages/*/composer.json`. Root `composer.json` is the meta-package (`clearsoft/easysql-sdk`):
it `require`s client + schema-generation and lists connectors/Laravel under `suggest` +
`require-dev`, resolving every package via `repositories: path`.

## Commands

```bash
make generate   # Re-generate packages/client from spec
make lint       # PHP syntax check (packages + scripts)
make analyse    # PHPStan static analysis (level 5, packages/)
make test       # PHPUnit (all packages; per-suite: --testsuite client|common|connectors-mysql|connectors-postgres|connectors-sqlite|schema-generation|laravel)
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
