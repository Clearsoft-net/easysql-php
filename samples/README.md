# Samples

Runnable examples for every package of the EasySQL PHP SDK. Each file is a
standalone script that starts with the exact command to run it.

```bash
composer install
php samples/09-schema-generation.php   # no credentials needed
```

| File | Package(s) | Needs |
|---|---|---|
| [`01-client-basic.php`](01-client-basic.php) | `easysql-client` | API access token |
| [`02-token-store-refresh.php`](02-token-store-refresh.php) | `easysql-client` | access + refresh token |
| [`03-manage-connectors.php`](03-manage-connectors.php) | `easysql-client` | API access token |
| [`04-run-queries.php`](04-run-queries.php) | `easysql-client` | API access token + connector id |
| [`05-dashboard-billing.php`](05-dashboard-billing.php) | `easysql-client` | API access token |
| [`06-conn-mysql-sync.php`](06-conn-mysql-sync.php) | mysql connector + schema-generation + client | MySQL + token + connector id |
| [`07-conn-postgres-sync.php`](07-conn-postgres-sync.php) | postgres connector + schema-generation + client | PostgreSQL + token + connector id |
| [`08-conn-sqlite-sync.php`](08-conn-sqlite-sync.php) | sqlite connector + schema-generation + client | SQLite file + token + connector id |
| [`09-schema-generation.php`](09-schema-generation.php) | `easysql-schema-generation` | nothing |
| [`10-laravel.php`](10-laravel.php) | `easysql-laravel` | a Laravel application |
| [`11-error-handling.php`](11-error-handling.php) | `easysql-client` | API access token |
| [`12-api-keys-schema-only.php`](12-api-keys-schema-only.php) | `easysql-client` | API access token |
| [`13-local-execution-flow.php`](13-local-execution-flow.php) | client + mysql connector | MySQL + token + connector id |
| [`14-typed-dtos.php`](14-typed-dtos.php) | `easysql-client` | API access token (+ refresh token) |

## Environment variables

| Variable | Used by | Default |
|---|---|---|
| `EASYSQL_BASE_URL` | all client samples | `https://api.easysql.net` |
| `EASYSQL_ACCESS_TOKEN` | 01, 03, 04, 05, 06, 07, 08, 11, 12, 13, 14 | — |
| `EASYSQL_REFRESH_TOKEN` | 02, 14 | — |
| `CONNECTOR_ID` | 03 (synced), 04, 06, 07, 08, 13, 14 | `conn_abc123` |
| `MYSQL_HOST/PORT/USER/PASSWORD/DATABASE` | 06 | `127.0.0.1:3306/root//shop` |
| `PG_HOST/PORT/USER/PASSWORD/DATABASE/SSLMODE` | 07 | `127.0.0.1:5432/postgres//postgres/prefer` |
| `SQLITE_PATH` | 08 | `:memory:` |

The connector samples send **only the schema** to the API — database credentials
stay on the machine and are discarded when the connection closes.

## Local databases

The integration-test databases from `compose.yaml` can be used directly:

```bash
make db-up

MYSQL_HOST=127.0.0.1 MYSQL_DATABASE=easysql_test \
PG_HOST=127.0.0.1 PG_PORT=5433 PG_DATABASE=easysql_test PG_PASSWORD=postgres \
php samples/06-conn-mysql-sync.php
```
