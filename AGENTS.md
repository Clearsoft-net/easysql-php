# AGENTS.md

## Project overview

This is the official **PHP SDK** for the EasySQL API. The main code (`src/Client.php` and `src/Models/`) is **auto-generated** from the OpenAPI specification at `https://api.easysql.net/openapi.json`. Manual edits to generated files will be overwritten.

## Architecture

```
src/
├── Client.php            # 🤖 Generated — named API methods
├── Models/               # 🤖 Generated — request/response DTOs
│   ├── LoginRequest.php
│   ├── TokenResponse.php
│   └── ...
├── token-store.php       # ✋ Hand-written — EasySQLClient + TokenStoreInterface
└── Exceptions/           # ✋ Hand-written (future)

scripts/
├── generate.php          # Orchestration — downloads spec, drives generation
├── client.php.tpl        # Template for Client.php with {{IMPLEMENTATION}} placeholder
└── lib/
    ├── extract.php       # Spec parsing → GeneratedMethod[]
    ├── build.php         # Code generation → method bodies + models
    └── docs.php          # Markdown docs generation → docs/API.md

tests/
├── ClientTest.php        # PHPUnit tests with Guzzle MockHandler

docs/
└── API.md                # 🤖 Generated — API reference grouped by category

.github/workflows/
└── generate-sdk.yml      # CI — triggered by repository_dispatch from easysql-api
```

## Generation flow

1. `make generate` → `php scripts/generate.php`
2. Downloads `openapi.json` from `EASYSQL_API_URL` (env var or `--spec-url` flag)
3. `extractMethods(spec)` — walks every path/method → builds `GeneratedMethod[]`
4. `buildImpl(methods)` → renders full method bodies with docblocks
5. Injects into `client.php.tpl` → writes `src/Client.php`
6. `generateModels(spec)` → writes `src/Models/*.php`
7. `generateDocs(methods)` → writes `docs/API.md`

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
| Body only | `$client->login(['email' => ..., 'password' => ...])` |
| Path only | `$client->getConnector('conn_123')` |
| Query only | `$client->listQueries(['page' => 1])` |
| Body + Path | `$client->updateConnector(['name' => 'X'], 'conn_123')` |

### Template placeholders

`{{IMPLEMENTATION}}` in `client.php.tpl` is replaced with the full generated method blocks (docblock + signature + body).

## Commands

```bash
make generate   # Re-generate from spec
make lint       # PHP syntax check
make test       # PHPUnit
make build      # generate + lint + test
make clean      # Remove generated files
```

## CI

- Trigger: `repository_dispatch` (from `easysql-api` spec update) or `workflow_dispatch`
- Steps: checkout → setup PHP → `make install` → `make generate` → detect changes → open PR
