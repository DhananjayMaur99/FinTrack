# FinTrack API Endpoints

This document describes all public API endpoints, required headers, body formats, query parameters, and example requests/responses.

## Conventions

- Base URL: http(s)://<host>/api
- Content-Type: application/json (use raw JSON bodies; multipart/form-data is not supported)
- Accept: application/json
- Authentication: Bearer token via Laravel Sanctum for protected routes
- Optional timezone header: `X-Timezone: Asia/Kolkata` (IANA name)
- Monetary values are returned as strings for precision

---

## Authentication

### POST /api/register
Register a new user.

Headers:
- Content-Type: application/json
- Accept: application/json

Body (JSON):
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}

Response 201:
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2025-11-04T10:00:00.000000Z",
    "updated_at": "2025-11-04T10:00:00.000000Z"
  },
  "token": "1|abc123...",
  "expires_at": "2025-11-04T11:00:00.000000Z",
  "expires_in": 3600
}

Validation:
- name: required, string, max:255
- email: required, string, email, unique:users
- password: required, confirmed, min:8

---

### POST /api/login
Authenticate an existing user.

Headers:
- Content-Type: application/json
- Accept: application/json

Body (JSON):
{
  "email": "john@example.com",
  "password": "password123"
}

Response 200:
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "2|xyz789...",
  "expires_at": "2025-11-04T11:00:00.000000Z",
  "expires_in": 3600
}

Errors:
- 401: { "message": "Invalid credentials" }

---

### POST /api/refresh
Rotate the current token and get a new one (revokes the old token).

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{
  "token": "3|newToken...",
  "expires_at": "2025-11-04T12:00:00.000000Z",
  "expires_in": 3600
}

---

### POST /api/logout
Revoke the current access token.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{ "message": "Logged out successfully" }

---

### GET /api/user
Get the currently authenticated user.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "timezone": "Asia/Kolkata",
  "created_at": "2025-11-04T10:00:00.000000Z",
  "updated_at": "2025-11-04T10:00:00.000000Z"
}

---

## Categories

### GET /api/categories
List all categories for the authenticated user.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{
  "data": [
    { "id": 1, "user_id": 1, "name": "Groceries", "icon": "cart" }
  ]
}

---

### POST /api/categories
Create a category.

Headers:
- Authorization: Bearer {token}
- Content-Type: application/json
- Accept: application/json

Body (JSON):
{
  "name": "Entertainment",
  "icon": "movie"
}

Response 201:
{
  "data": { "id": 2, "user_id": 1, "name": "Entertainment", "icon": "movie" }
}

Validation:
- name: required, string, max:255
- icon: nullable, string, max:255

---

### GET /api/categories/{id}
Get a category by ID (must belong to the user).

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{
  "data": { "id": 1, "user_id": 1, "name": "Groceries", "icon": "cart" }
}

Errors:
- 403: Not authorized
- 404: Not found

---

### PUT /api/categories/{id}
Update a category.

Headers:
- Authorization: Bearer {token}
- Content-Type: application/json
- Accept: application/json

Body (JSON):
{
  "name": "Supermarket",
  "icon": "shopping-bag"
}

Response 200:
{
  "data": { "id": 1, "user_id": 1, "name": "Supermarket", "icon": "shopping-bag" }
}

---

### DELETE /api/categories/{id}
Soft-delete a category.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 204: (no content)

---

## Transactions

### GET /api/transactions
List transactions (paginated).

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Query params:
- page: integer (default 1)
  (per-page is fixed to 15 by the server’s default pagination)

Response 200:
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "category_id": 1,
      "amount": "45.99",
      "description": "Weekly groceries",
      "date": "2025-11-04",           
      "date_local": "2025-11-04",     
      "occurred_at_utc": "2025-11-04T10:00:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}

---

### POST /api/transactions
Create a transaction. If `date` is omitted, the server uses “today” in the user’s timezone.

Headers:
- Authorization: Bearer {token}
- Content-Type: application/json
- Accept: application/json
- Optional: X-Timezone: Asia/Kolkata

Body (JSON):
{
  "category_id": 1,
  "a    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"mount": 45.99,
  "description": "Weekly groceries",
  "date": "2025-11-04"  
}

Response 201:
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "amount": "45.99",
    "description": "Weekly groceries",
    "date": "2025-11-04",
    "date_local": "2025-11-04",
    "occurred_at_utc": "2025-11-04T10:00:00.000000Z"
  }
}

Validation:
- amount: required, numeric, min:0.01
- date: sometimes, date (optional)
- description: nullable, string
- category_id: nullable, exists:categories (must belong to user and not be soft-deleted)

---

### GET /api/transactions/{id}
Get a transaction by ID (must belong to the user).

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "amount": "45.99",
    "description": "Weekly groceries",
    "date": "2025-11-04",
    "date_local": "2025-11-04",
    "occurred_at_utc": "2025-11-04T10:00:00.000000Z"
  }
}

---

### PUT /api/transactions/{id}
Update a transaction. If `date` is changed, the server updates `occurred_at_utc` to the current UTC timestamp for audit.

Headers:
- Authorization: Bearer {token}
- Content-Type: application/json
- Accept: application/json

Body (JSON):
{
  "amount": 52.50,
  "description": "Updated description"
}

Response 200:
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "amount": "52.50",
    "description": "Updated description",
    "date": "2025-11-04",
    "date_local": "2025-11-04",
    "occurred_at_utc": "2025-11-04T10:30:00.000000Z"
  }
}

Validation:
- amount: sometimes, numeric, min:0.01
- date: sometimes, date
- description: sometimes, nullable, string
- category_id: sometimes, nullable, exists:categories (must belong to user and not be soft-deleted)

---

### DELETE /api/transactions/{id}
Soft-delete a transaction.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 204: (no content)

---

## Budgets

### GET /api/budgets
List budgets for the authenticated user.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "category_id": 1,
      "limit": "500.00",
      "period": "monthly",
      "start_date": "2025-11-01",
      "end_date": "2025-11-30"
    }
  ]
}

---

### POST /api/budgets
Create a budget. If `end_date` is omitted, the server auto-computes it based on `period` and `start_date`:
- monthly: end_date = start_date + 1 month - 1 day
- yearly: end_date = start_date + 1 year - 1 day

Headers:
- Authorization: Bearer {token}
- Content-Type: application/json
- Accept: application/json

Body (JSON):
{
  "category_id": 1,
  "limit": 500.0,
  "period": "monthly",
  "start_date": "2025-11-01",
  "end_date": "2025-11-30" // optional; will be computed if omitted
}

Response 201:
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "limit": "500.00",
    "period": "monthly",
    "start_date": "2025-11-01",
    "end_date": "2025-11-30",
    "is_open_ended": false,
    "progress_stats": {
      "limit": 500.0,
      "spent": 145.5,
      "remaining": 354.5,
      "progress_percent": 29.1,
      "is_over_budget": false
    }
  }
}

Validation:
- limit: required, numeric, min:0.01
- period: required, in: monthly, yearly
- start_date: required, date
- end_date: nullable, date, after_or_equal:start_date (auto-computed if omitted)
- category_id: nullable, exists:categories (must belong to user and not be soft-deleted)

---

### GET /api/budgets/{id}
Get a budget by ID with progress stats.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 200:
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "limit": "500.00",
    "period": "monthly",
    "start_date": "2025-11-01",
    "end_date": "2025-11-30",
    "progress_stats": { ... }
  }
}

---

### PUT /api/budgets/{id}
Update a budget.

Headers:
- Authorization: Bearer {token}
- Content-Type: application/json
- Accept: application/json

Body (JSON):
{
  "limit": 750.0,
  "period": "yearly"
}

Response 200:
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "limit": "750.00",
    "period": "yearly",
    "start_date": "2025-11-01",
    "end_date": "2025-11-30",
    "progress_stats": { ... }
  }
}

---

### DELETE /api/budgets/{id}
Delete a budget.

Headers:
- Authorization: Bearer {token}
- Accept: application/json

Response 204: (no content)

---

## Errors

Examples:

401 Unauthorized
{ "message": "Unauthenticated." }

403 Forbidden
{ "message": "This action is unauthorized." }

404 Not Found
{ "message": "Resource not found." }

422 Validation Error
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "amount": ["The amount must be at least 0.01."]
  }
}

500 Server Error
{ "message": "Server Error" }

---

## Notes

- All request bodies must be raw JSON (no form-data).
- Use `Authorization: Bearer {token}` for protected endpoints.
- Tokens expire after 60 minutes by default; `expires_at` and `expires_in` are returned by auth endpoints.
- Transactions accept an optional `X-Timezone` header; if `date` is omitted, the server uses “today” in that timezone.