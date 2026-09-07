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

### `changePassword()`

Change password.

```
POST /v1/auth/change-password
```

**Parameters:**

- `body` — `ChangePasswordRequest`


---

### `deleteMe()`

Delete current user (hard delete).

```
DELETE /v1/auth/me
```

**Returns:** `204 No Content`

---

### `forgotPassword()`

Trigger password reset email (HMAC-signed token, 1h expiry). Always 200; silent if email unknown..

```
POST /v1/auth/forgot-password
```

**Parameters:**



---

### `login()`

Login (returns access + refresh tokens).

```
POST /v1/auth/login
```

**Parameters:**

- `body` — `UserLogin`

**Returns:** `TokenResponse`

---

### `me()`

Get current user (with active plan).

```
GET /v1/auth/me
```

**Returns:** `UserMeResponse`

---

### `refresh()`

Refresh tokens.

```
POST /v1/auth/refresh
```

**Parameters:**

- `body` — `TokenRefresh`

**Returns:** `TokenResponse`

---

### `register()`

Register new user.

```
POST /v1/auth/register
```

**Parameters:**

- `body` — `UserCreate`

**Returns:** `UserResponse`

---

### `resendVerification()`

Resend verification email (always 200; silent if email unknown).

```
POST /v1/auth/resend-verification
```

**Parameters:**



---

### `resetPassword()`

Apply new password via HMAC-signed token.

```
POST /v1/auth/reset-password
```

**Parameters:**



---

### `updateMe()`

Update current user (name, locale).

```
PATCH /v1/auth/me
```

**Parameters:**

- `body` — `UserUpdate`

**Returns:** `UserResponse`

---

### `verifyEmail()`

Verify email via HMAC-signed token (24h expiry).

```
POST /v1/auth/verify-email
```

**Parameters:**



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

**Returns:** `204 No Content`

---

### `getConnector()`

Get connector.

```
GET /v1/connectors/{connector_id}
```

**Returns:** `ConnectorResponse`

---

### `getConnectorSchema()`

Get cached schema.

```
GET /v1/connectors/{connector_id}/schema
```

**Returns:** `ConnectorSchemaResponse`

---

### `getSuggestions()`

LLM-generated Portuguese business questions.

```
GET /v1/connectors/{connector_id}/suggestions
```

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


---

### `updateConnector()`

Update connector.

```
PATCH /v1/connectors/{connector_id}
```

**Parameters:**

- `body` — `ConnectorUpdate`

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

**Returns:** `204 No Content`

---

### `getFeedback()`

Get feedback for a query.

```
GET /v1/feedbacks/{query_id}
```

**Returns:** `FeedbackResponse`

---

### `upsertFeedback()`

Upsert feedback for a query.

```
PUT /v1/feedbacks/{query_id}
```

**Parameters:**

- `body` — `FeedbackCreate`

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

## Queries

### `answerQuery()`

WP plugin submits locally-executed result (API key only).

```
POST /v1/queries/{query_id}/answer
```

**Parameters:**

- `body` — `LocalResultRequest`

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


---

