# API Reference

> Auto-generated from the OpenAPI spec. Do not edit manually.

## Endpoints

- [Analytics](#analytics)
- [Api-keys](#api-keys)
- [Auth](#auth)
- [Billing](#billing)
- [Connections](#connections)
- [Dashboard](#dashboard)
- [Feedbacks](#feedbacks)
- [Flags](#flags)
- [Health](#health)
- [Integrations](#integrations)
- [Internal](#internal)
- [Oidc](#oidc)
- [Queries](#queries)

## Analytics

### `listAnalyticsQueries()`

User's queries from Cloudflare Analytics Engine (last N days).

```
GET /v1/analytics/queries
```

**Parameters:**

- `limit` — `integer` (optional, query)
- `days` — `integer` (optional, query)

**Returns:** `AnalyticsQueriesResponse`

---

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

### `cancel()`

Schedule subscription cancellation at period end.

```
POST /v1/billing/cancel
```

**Returns:** `SubscriptionStateResponse`

---

### `changePlan()`

Change plan (monthly to annual) with immediate proration.

```
POST /v1/billing/change-plan
```

**Parameters:**

- `price_id` — `string` (required, query)

**Returns:** `ChangePlanResponse`

---

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

List plans (Free/Starter/Pro/Max).

```
GET /v1/billing/plan
```


---

### `getSubscription()`

Current subscription details (period, amount, card).

```
GET /v1/billing/subscription
```

**Returns:** `SubscriptionDetailsResponse`

---

### `getUsage()`

Current usage as percentage per window (5h/7d).

```
GET /v1/billing/usage
```

**Returns:** `UsageResponse`

---

### `listInvoices()`

Payment history for the user's Stripe customer.

```
GET /v1/billing/invoices
```

**Parameters:**

- `limit` — `integer` (optional, query)

**Returns:** `InvoiceListResponse`

---

### `portal()`

Create Stripe Customer Portal session.

```
POST /v1/billing/portal
```

**Parameters:**

- `price_id` — `string` (optional, query)

**Returns:** `PortalResponse`

---

### `resume()`

Revert a scheduled cancellation.

```
POST /v1/billing/resume
```

**Returns:** `SubscriptionStateResponse`

---

### `webhook()`

Stripe webhook (verify HMAC SHA-256).

```
POST /v1/billing/webhook
```

**Parameters:**



---

## Connections

### `autocomplete()`

LLM autocomplete for partial question.

```
POST /v1/connections/{connection_id}/autocomplete
```

**Parameters:**

- `body` — `AutocompleteRequest`
- `connection_id` — `string (uuid)` (required, path)

**Returns:** `SuggestionsResponse`

---

### `createConnection()`

Create schema-only connection.

```
POST /v1/connections
```

**Parameters:**

- `body` — `ConnectionCreate`

**Returns:** `ConnectionResponse`

---

### `deleteConnection()`

Delete connection.

```
DELETE /v1/connections/{connection_id}
```

**Parameters:**

- `connection_id` — `string (uuid)` (required, path)

**Returns:** `204 No Content`

---

### `getConnection()`

Get connection.

```
GET /v1/connections/{connection_id}
```

**Parameters:**

- `connection_id` — `string (uuid)` (required, path)

**Returns:** `ConnectionResponse`

---

### `getConnectionSchema()`

Get cached schema.

```
GET /v1/connections/{connection_id}/schema
```

**Parameters:**

- `connection_id` — `string (uuid)` (required, path)

**Returns:** `ConnectionSchemaResponse`

---

### `getSuggestions()`

LLM-generated Portuguese business questions.

```
GET /v1/connections/{connection_id}/suggestions
```

**Parameters:**

- `connection_id` — `string (uuid)` (required, path)

**Returns:** `SuggestionsResponse`

---

### `listConnections()`

List connections.

```
GET /v1/connections
```


---

### `syncConnection()`

Push schema from client (refresh).

```
POST /v1/connections/{connection_id}/sync
```

**Parameters:**

- `body` — `ConnectionSyncRequest`
- `connection_id` — `string (uuid)` (required, path)


---

### `updateConnection()`

Update connection.

```
PATCH /v1/connections/{connection_id}
```

**Parameters:**

- `body` — `ConnectionUpdate`
- `connection_id` — `string (uuid)` (required, path)

**Returns:** `ConnectionResponse`

---

## Dashboard

### `dashboardStats()`

Dashboard stats (connections, queries, top usage).

```
GET /v1/dashboard/stats
```

**Returns:** `DashboardStats`

---

## Feedbacks

### `createFeedback()`

Create feedback (append-only; no read/update/delete).

```
POST /v1/feedbacks
```

**Parameters:**

- `body` — `FeedbackCreate`

**Returns:** `FeedbackResponse`

---

## Flags

### `getFlags()`

Feature flags for the current user (Cloudflare Flagship).

```
GET /v1/flags
```

**Returns:** `FlagsResponse`

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

## Integrations

### `getIntegration()`

Get one integration by id.

```
GET /v1/integrations/{integration_id}
```

**Parameters:**

- `integration_id` — `string` (required, path)

**Returns:** `Integration`

---

### `listIntegrations()`

List the integration catalog.

```
GET /v1/integrations
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

Get query detail.

```
GET /v1/queries/{query_id}
```

**Parameters:**

- `query_id` — `string (uuid)` (required, path)

**Returns:** `QueryResponse`

---

### `listQueries()`

Cursor-paginated query history (R2 Data Catalog).

```
GET /v1/queries
```

**Parameters:**

- `limit` — `integer` (optional, query)
- `cursor` — `string` (optional, query)

**Returns:** `HistoryPage`

---

