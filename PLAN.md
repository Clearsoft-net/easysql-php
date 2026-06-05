# PLAN — easysql-sdk-php

Roadmap for building a PHP SDK for the EasySQL API, following the same auto-generated architecture used in easysql-sdk-ts.

## 1. What we did in TypeScript (reference)

### Architecture

```
src/
├── api-types.ts          # 🤖 openapi-typescript → types from OpenAPI spec
├── client.ts             # 🤖 template-based → named methods from spec
└── index.ts              # ✋ public exports

scripts/
├── generate.ts           # orchestration
├── client.ts.tpl         # template for client.ts
└── lib/
    ├── types.ts          # GeneratedMethod interface
    ├── extract.ts        # spec parsing → methods list
    ├── build.ts          # code generation
    └── docs.ts           # markdown docs generation

docs/
└── API.md                # 🤖 clean reference grouped by category
```

### Generation flow

1. `make generate` → `bun run scripts/generate.ts`
2. Reads `.env` → `EASYSQL_API_URL/openapi.json`
3. `openapiTS(specUrl)` → writes `src/api-types.ts` (raw types)
4. `retryFetch(specUrl)` → parses spec JSON
5. `extractMethods(spec)` → walks every path/method → builds `GeneratedMethod[]`
6. `buildInterface` + `buildImpl` → injects into `client.ts.tpl`
7. `generateDocs(methods)` → writes `docs/API.md`

### Named methods

Operation IDs from the spec:
```
login_v1_auth_login_post
me_v1_auth_me_get
list_connectors_v1_connectors_get
create_connector_v1_connectors_post
get_connector_v1_connectors__connector_id__get
...
```

Derived method names (strip `_v\d+_` and HTTP method, snake→camelCase):
```
login()
me()
listConnectors()
createConnector()
getConnector()
...
```

### Parameter flattening

| Spec params | Consumer writes |
|---|---|
| Body only | `client.login({ email, password })` |
| Path only | `client.getConnector({ connector_id })` |
| Query only | `client.listQueries({ page, per_page })` |
| Body + Path | `client.updateConnector(body, { path })` — kept explicit |

### CI

- Trigger: `repository_dispatch` (from easysql-api) or `workflow_dispatch`
- Steps: `bun install` → `make generate` → `make typecheck` → `make test` → `make build`
- Opens PR with changes

## 2. PHP translation

### Stack recommendation

| Layer | Choice | Why |
|---|---|---|
| Runtime | PHP 8.2+ | Modern, typed |
| HTTP client | GuzzleHTTP | Industry standard |
| Code generation | `openapi-generator-cli` (`-g php`) | Fastest path; generates models + API classes |
| **Or** custom template | Custom PHP script + `.tpl` | Idiomatic, no Java, full control |
| Task runner | Makefile | Same as TS |
| CI | GitHub Actions | Same as TS |
| Tests | PHPUnit | Standard |
| Docs | Markdown (same logic as `docs.ts`) | Same as TS |

### Option A — `openapi-generator-cli` (quick)

```bash
npx @openapitools/openapi-generator-cli generate \
  -g php \
  -i https://api.easysql.net/openapi.json \
  -o src/ \
  --additional-properties=packageName=Clearsoft/EasySQL
```

- Output: Guzzle-based client with models, enums, and API classes.
- **Pro**: ready in 5 minutes. **Con**: bloated, Java in CI.

### Option B — Custom template (like TS, recommended)

Build a PHP script that:

1. Downloads `openapi.json`
2. Generates models as PHP classes (`src/Models/LoginRequest.php`)
3. Generates `src/Client.php` with named methods
4. Generates `docs/API.md`

```php
// Generated Client.php
namespace Clearsoft\EasySQL;

class Client {
    public function login(array $body): array { ... }
    public function me(): array { ... }
    public function listConnectors(): array { ... }
    public function getConnector(string $connectorId): array { ... }
    // ...
}
```

### File structure (Option B)

```
src/
├── Models/               # 🤖 generated
│   ├── LoginRequest.php
│   ├── TokenResponse.php
│   └── ...
├── Client.php            # 🤖 template-based
└── Exceptions/
    └── ApiException.php

scripts/
├── generate.php          # orchestration
└── client.php.tpl        # template

docs/
└── API.md                # 🤖

tests/
└── ClientTest.php        # PHPUnit

composer.json
Makefile
.env.example
```

## 3. Key decisions to replicate from TS

### 3.1 Operation ID → method name

Same algorithm: strip `_v\d+_` marker and HTTP method suffix, convert snake_case to camelCase.

### 3.2 Parameter flattening

Same logic: if only one param type (body/path/query), flatten. Multiple types stay explicit.

### 3.3 Template with placeholders

`{{INTERFACE}}` → method signatures (for docs/IDE)
`{{IMPLEMENTATION}}` → method bodies (wrapping Guzzle)

### 3.4 CI workflow

Identical to TS: `repository_dispatch` → `make generate` → `composer install` → `make test` → `make build` → open PR.

### 3.5 Documentation

Same `docs/API.md` format: grouped by category, real parameter names from spec.

### 3.6 Retry logic

Same exponential backoff (3 retries) on spec download.

## 4. Implementation order

| # | Step | Est. effort |
|---|---|---|
| 1 | `composer init` + install Guzzle, PHPUnit | 5 min |
| 2 | `scripts/generate.php` — download spec, extract methods | 30 min |
| 3 | `scripts/client.php.tpl` — template with `{{INTERFACE}}` + `{{IMPLEMENTATION}}` | 20 min |
| 4 | Generate `src/Client.php` + `docs/API.md` | 30 min |
| 5 | `Makefile` — same targets as TS | 10 min |
| 6 | `.github/workflows/generate-sdk.yml` — same as TS | 10 min |
| 7 | `tests/ClientTest.php` — Guzzle mock | 30 min |
| 8 | `AGENTS.md`, `MEMORY.md`, `README.md` | 20 min |

**Total**: ~2.5h para um SDK PHP idiomático e auto-gerado.
