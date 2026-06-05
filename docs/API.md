# API Reference

> Auto-generated from the OpenAPI spec. Do not edit manually.

## Endpoints

- [Api-keys](#api-keys)
- [Auth](#auth)
- [Billing](#billing)
- [Connectors](#connectors)
- [Dashboard](#dashboard)
- [Default](#default)
- [Queries](#queries)

## Api-keys

### `createApiKey()` 🔒

Create Api Key.

```
POST /v1/api-keys
```

**Parameters:**

- `body` — `ApiKeyCreate`

**Returns:** `ApiKeyCreated`

---

### `deleteApiKey()` 🔒

Delete Api Key.

```
DELETE /v1/api-keys/{key_id}
```

**Parameters:**

- `key_id` — `string (uuid)` (required, path)

**Returns:** `204 No Content`

---

### `listApiKeys()` 🔒

List Api Keys.

```
GET /v1/api-keys
```


---

## Auth

### `changePassword()` 🔒

Change Password.

```
POST /v1/auth/change-password
```

**Parameters:**

- `body` — `ChangePasswordRequest`


---

### `deleteMe()` 🔒

Delete Me.

```
DELETE /v1/auth/me
```

**Returns:** `204 No Content`

---

### `login()`

Login.

```
POST /v1/auth/login
```

**Parameters:**

- `body` — `UserLogin`

**Returns:** `TokenResponse`

---

### `me()` 🔒

Me.

```
GET /v1/auth/me
```

**Returns:** `UserResponse`

---

### `refresh()`

Refresh.

```
POST /v1/auth/refresh
```

**Parameters:**

- `body` — `TokenRefresh`

**Returns:** `TokenResponse`

---

### `register()`

Register.

```
POST /v1/auth/register
```

**Parameters:**

- `body` — `UserCreate`

**Returns:** `UserResponse`

---

### `updateMe()` 🔒

Update Me.

```
PATCH /v1/auth/me
```

**Parameters:**

- `body` — `UserUpdate`

**Returns:** `UserResponse`

---

## Billing

### `checkout()` 🔒

Checkout.

```
POST /v1/billing/checkout
```

**Parameters:**

- `price_id` — `string` (required, query)

**Returns:** `CheckoutResponse`

---

### `getPlan()` 🔒

Get Plan.

```
GET /v1/billing/plan
```


---

### `portal()` 🔒

Portal.

```
POST /v1/billing/portal
```

**Returns:** `PortalResponse`

---

## Connectors

### `createConnector()` 🔒

Create Connector.

```
POST /v1/connectors
```

**Parameters:**

- `body` — `ConnectorCreate`

**Returns:** `ConnectorResponse`

---

### `deleteConnector()` 🔒

Delete Connector.

```
DELETE /v1/connectors/{connector_id}
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)

**Returns:** `204 No Content`

---

### `getConnector()` 🔒

Get Connector.

```
GET /v1/connectors/{connector_id}
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)

**Returns:** `ConnectorResponse`

---

### `getConnectorSchema()` 🔒

Get Connector Schema.

```
GET /v1/connectors/{connector_id}/schema
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)

**Returns:** `ConnectorSchemaResponse`

---

### `listConnectors()` 🔒

List Connectors.

```
GET /v1/connectors
```


---

### `syncConnector()` 🔒

Sync Connector.

```
POST /v1/connectors/{connector_id}/sync
```

**Parameters:**

- `connector_id` — `string (uuid)` (required, path)


---

### `testConnector()` 🔒

Test Connector.

```
POST /v1/connectors/test
```

**Parameters:**

- `body` — `ConnectorTestRequest`

**Returns:** `ConnectorTestResponse`

---

### `updateConnector()` 🔒

Update Connector.

```
PATCH /v1/connectors/{connector_id}
```

**Parameters:**

- `body` — `ConnectorUpdate`
- `connector_id` — `string (uuid)` (required, path)

**Returns:** `ConnectorResponse`

---

## Dashboard

### `dashboardStats()` 🔒

Dashboard Stats.

```
GET /v1/dashboard/stats
```

**Returns:** `DashboardStats`

---

## Default

### `health()`

Health V1.

```
GET /v1/health
```


---

### `healthHealth()`

Health.

```
GET /health
```


---

## Queries

### `createQuery()` 🔒

Create Query.

```
POST /v1/queries
```

**Parameters:**

- `body` — `QueryRequest`

**Returns:** `QueryResponse`

---

### `getQuery()` 🔒

Get Query.

```
GET /v1/queries/{query_id}
```

**Parameters:**

- `query_id` — `string (uuid)` (required, path)

**Returns:** `QueryResponse`

---

### `listQueries()` 🔒

List Queries.

```
GET /v1/queries
```

**Parameters:**

- `page` — `integer` (optional, query)
- `per_page` — `integer` (optional, query)

**Returns:** `PaginatedQueries`

---

