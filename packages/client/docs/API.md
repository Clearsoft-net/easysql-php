# API Reference

> Auto-generated from the OpenAPI spec. Do not edit manually.

## Endpoints

- [Api-keys](#api-keys)
- [Auth](#auth)
- [Billing](#billing)
- [Connectors](#connectors)
- [Dashboard](#dashboard)
- [Feedbacks](#feedbacks)
- [Health](#health)
- [Internal](#internal)
- [Oidc](#oidc)
- [Queries](#queries)

## Api-keys

### `createApiKey()`

Create API key (returns full key once).

```
POST /v1/api-keys
```

**Parameters:**

- `body` — `ApiKeyCreate`

**Returns:** `ApiKeyCreated`

---

### `deleteApiKey()`

Revoke API key (soft delete).

```
DELETE /v1/api-keys/{key_id}
```

**Parameters:**

- `key_id` — `string (uuid)` (required, path)

**Returns:** `204 No Content`

---

### `listApiKeys()`

List API keys (without secret).

```
GET /v1/api-keys
```


---

## Auth

### `deleteMe()`

Delete current user (hard delete).

```
DELETE /v1/auth/me
```

**Returns:** `204 No Content`

---

### `logout()`

RP-initiated OIDC logout — returns URL to redirect the browser to Authentik end_session_endpoint.

```
POST /v1/auth/logout
```

**Returns:** `OidcLogoutResponse`

---

### `me()`

Get current user (claims from OIDC ID token + DB state).

```
GET /v1/auth/me
```

**Returns:** `UserMeResponse`

---

### `refresh()`

Rotate our access+refresh JWT pair.

```
POST /v1/auth/refresh
```

**Parameters:**

- `body` — `TokenRefresh`

**Returns:** `TokenResponse`

---

### `updateMe()`

Update current user locale (name/email come from OIDC ID token).

```
PATCH /v1/auth/me
```

**Parameters:**

- `body` — `UserUpdate`

**Returns:** `UserMeResponse`

---

## Billing

### `checkout()`

Create Stripe Checkout session.

```
POST /v1/billing/checkout
```

**Parameters:**

- `price_id` — `string` (required, query)

**Returns:** `CheckoutResponse`

---

### `getPlan()`

List plans (Free/Starter/Pro/Business).

```
GET /v1/billing/plan
```


---

### `getUsage()`

Current usage vs plan limits (daily/weekly/monthly).

```
GET /v1/billing/usage
```

**Returns:** `UsageResponse`

---

### `portal()`

Create Stripe Customer Portal session.

```
POST /v1/billing/portal
```

**Returns:** `PortalResponse`

---

### `webhook()`

Stripe webhook (verify HMAC SHA-256).

```
POST /v1/billing/webhook
```

**Parameters:**



---

## Connectors

### `autocomplete()`

LLM autocomplete for partial question.

```
POST /v1/connectors/{connector_id}/autocomplete
```

**Parameters:**

- `body` — `AutocompleteRequest`
- `connector_id` — `string (uuid)` (required, path)

**Returns:** `SuggestionsResponse`

---

### `createConnector()`

Create schema-only connector.

```
POST /v1/connectors
```

**Parameters:**

- `body` — `ConnectorCreate`

**Returns:** `ConnectorResponse`

---

### `deleteConnector()`

Delete connector.

```
DELETE /v1/connectors/{connector_id}
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)

**Returns:** `204 No Content`

---

### `getConnector()`

Get connector.

```
GET /v1/connectors/{connector_id}
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)

**Returns:** `ConnectorResponse`

---

### `getConnectorSchema()`

Get cached schema.

```
GET /v1/connectors/{connector_id}/schema
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)

**Returns:** `ConnectorSchemaResponse`

---

### `getSuggestions()`

LLM-generated Portuguese business questions.

```
GET /v1/connectors/{connector_id}/suggestions
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)

**Returns:** `SuggestionsResponse`

---

### `listConnectors()`

List connectors.

```
GET /v1/connectors
```


---

### `syncConnector()`

Push schema from client (refresh).

```
POST /v1/connectors/{connector_id}/sync
```

**Parameters:**

- `body` — `ConnectorSyncRequest`
- `connector_id` — `string (uuid)` (required, path)


---

### `updateConnector()`

Update connector.

```
PATCH /v1/connectors/{connector_id}
```

**Parameters:**

- `body` — `ConnectorUpdate`
- `connector_id` — `string (uuid)` (required, path)

**Returns:** `ConnectorResponse`

---

## Dashboard

### `dashboardStats()`

Dashboard stats (connectors, queries, top usage).

```
GET /v1/dashboard/stats
```

**Returns:** `DashboardStats`

---

## Feedbacks

### `deleteFeedback()`

Delete feedback for a query.

```
DELETE /v1/feedbacks/{query_id}
```

**Parameters:**

- `query_id` — `string (uuid)` (required, path)

**Returns:** `204 No Content`

---

### `getFeedback()`

Get feedback for a query.

```
GET /v1/feedbacks/{query_id}
```

**Parameters:**

- `query_id` — `string (uuid)` (required, path)

**Returns:** `FeedbackResponse`

---

### `upsertFeedback()`

Upsert feedback for a query.

```
PUT /v1/feedbacks/{query_id}
```

**Parameters:**

- `body` — `FeedbackCreate`
- `query_id` — `string (uuid)` (required, path)

**Returns:** `FeedbackResponse`

---

## Health

### `health()`

Health check (versioned).

```
GET /v1/health
```


---

### `healthHealth()`

Health check (legacy).

```
GET /health
```


---

## Internal

### `previewInternalEmail()`

Return rendered HTML for an email template. Dev-only (404 in production)..

```
GET /v1/internal/email/preview
```

**Parameters:**

- `template` — `string` (required, query)
- `name` — `string` (optional, query)
- `to` — `string` (optional, query)
- `token` — `string` (optional, query)


---

### `testInternalEmail()`

Render or send an email template via Resend. Dev-only (404 in production)..

```
POST /v1/internal/email/test
```

**Parameters:**

- `preview` — `string` (optional, query)


---

## Oidc

### `oidcCallback()`

OIDC redirect_uri — exchanges code for tokens, provisions user, sets cookie, redirects to /auth/complete.

```
GET /v1/auth/oidc/callback
```

**Parameters:**

- `code` — `string` (required, query)
- `state` — `string` (required, query)


---

### `oidcComplete()`

Frontend calls this on /auth/complete to read the one-shot cookie and get tokens.

```
POST /v1/auth/oidc/complete
```

**Parameters:**

- `body` — `OidcCompleteRequest`

**Returns:** `OidcCompleteResponse`

---

### `oidcStart()`

Begin OIDC Authorization Code + PKCE flow (302 to Authentik).

```
GET /v1/auth/oidc/start
```


---

## Queries

### `answerQuery()`

WP plugin submits locally-executed result (API key only).

```
POST /v1/queries/{query_id}/answer
```

**Parameters:**

- `body` — `LocalResultRequest`
- `query_id` — `string (uuid)` (required, path)

**Returns:** `QueryResponse`

---

### `createQuery()`

Ask question (generate SQL only — client executes).

```
POST /v1/queries
```

**Parameters:**

- `body` — `QueryRequest`

**Returns:** `QueryResponse`

---

### `getQuery()`

Get query detail (poll this to wait for status=ready).

```
GET /v1/queries/{query_id}
```

**Parameters:**

- `query_id` — `string (uuid)` (required, path)

**Returns:** `QueryResponse`

---

### `listQueries()`

Paginated query history.

```
GET /v1/queries
```

**Parameters:**

- `page` — `integer` (optional, query)
- `per_page` — `integer` (optional, query)

**Returns:** `PaginatedQueries`

---

### `streamQuery()`

SSE stream — emits QueryResponse every 500ms until ready/failed or 60s timeout.

```
GET /v1/queries/{query_id}/stream
```

**Parameters:**

- `query_id` — `string (uuid)` (required, path)


---

