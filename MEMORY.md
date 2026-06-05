# MEMORY.md

## Tech stack

- **PHP 8.2+** — typed, strict
- **GuzzleHTTP 7** — HTTP client
- **PHPUnit 11** — testing
- **No framework** — pure library

## Namespace

`Clearsoft\EasySQL\SDK\`

- `Clearsoft\EasySQL\SDK\Client` — auto-generated API client
- `Clearsoft\EasySQL\SDK\Models\*` — auto-generated DTOs
- `Clearsoft\EasySQL\SDK\EasySQLClient` — hand-written token management wrapper
- `Clearsoft\EasySQL\SDK\TokenStoreInterface` — hand-written token persistence interface

## Spec source

`https://api.easysql.net/openapi.json`

Local dev fallback: `http://localhost:8000/openapi.json`

The spec is a standard OpenAPI 3.1 document.

## Important constraints

1. **`src/Client.php` and `src/Models/` are auto-generated.** Never edit them manually.
2. **`docs/API.md` is auto-generated.** Same constraint.
3. The generation script lives in `scripts/`. It's written in plain PHP (no framework) and uses manual `require_once` for its library files.
4. The `client.php.tpl` template uses `{{IMPLEMENTATION}}` as the sole placeholder.
5. Path parameters are substituted using `preg_split` on `{param}` tokens — see `buildPathExpr()` in `scripts/lib/build.php`.

## Token management

- `EasySQLClient` wraps Guzzle with Bearer token injection, auto-refresh on 401, and persistent storage via `TokenStoreInterface`.
- It uses Guzzle middleware (`mapRequest` + `retry`) for the auth layer.
- This is separate from the generated `Client` class and lives in `src/token-store.php`.

## Tests

- `tests/ClientTest.php` uses Guzzle's `MockHandler` to simulate API responses.
- Reflection is used to inject the mock handler into the generated `Client`.
- Tests cover: body-only methods, path params, query params, explicit (body+path), void returns, error responses.

## CI

- Workflow: `.github/workflows/generate-sdk.yml`
- Triggered by `repository_dispatch` from `easysql-api` or manual `workflow_dispatch`
- Automatically opens a PR with generated changes
