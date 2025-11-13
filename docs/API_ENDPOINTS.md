# FinTrack API Endpoints

This document describes all public API endpoints, required headers, body formats, query parameters, and example requests/responses.

## Conventions

- **Base URL**: `http(s)://<host>/api`
- **Content-Type**: `application/json` (use raw JSON bodies; multipart/form-data is not supported)
- **Accept**: `application/json`
- **Authentication**: Bearer token via Laravel Sanctum for protected routes
- **Optional timezone header**: `X-Timezone: Asia/Kolkata` (IANA time zone identifier)
- **Monetary values**: Returned as strings for precision (e.g., `"45.99"`)
- **Dates**: ISO 8601 format (`YYYY-MM-DD`)
- **Timestamps**: ISO 8601 with microseconds and timezone (`2025-11-04T10:00:00.000000Z`)

---

## Authentication

### POST /api/register
Register a new user account. Optionally provide a timezone during registration.

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john.doe@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "timezone": "Asia/Kolkata"
}
```

**Request Body (minimal - timezone optional):**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "password": "SecurePass456!",
  "password_confirmation": "SecurePass456!"
}
```

**Success Response (201 Created):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john.doe@example.com",
    "created_at": "2025-11-12T10:00:00.000000Z",
    "updated_at": "2025-11-12T10:00:00.000000Z"
  },
  "token": "1|laravel_sanctum_abc123xyz789def456ghi012jkl345mno678pqr901stu234",
  "expires_at": "2025-11-12T11:00:00.000000Z",
  "expires_in": 3600
}
```

**Validation Rules:**
- `name`: required, string, max:255
- `email`: required, string, email, unique in users table
- `password`: required, string, min:8, must match password_confirmation
- `timezone`: optional, string, must be valid IANA timezone identifier (e.g., "Asia/Kolkata", "America/New_York", "UTC")

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

---

### POST /api/login
Authenticate an existing user and receive an access token.

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "email": "john.doe@example.com",
  "password": "SecurePass123!"
}
```

**Success Response (200 OK):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john.doe@example.com"
  },
  "token": "2|laravel_sanctum_xyz789abc123def456ghi012jkl345mno678pqr901stu234",
  "expires_at": "2025-11-12T11:00:00.000000Z",
  "expires_in": 3600
}
```

**Error Response (401 Unauthorized):**
```json
{
  "message": "Invalid credentials"
}
```

---

### POST /api/refresh
Rotate the current access token (revokes old token, issues new one).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (200 OK):**
```json
{
  "token": "3|laravel_sanctum_new789token123fresh456access890token234xyz567",
  "expires_at": "2025-11-12T12:00:00.000000Z",
  "expires_in": 3600
}
```

**Error Response (401 Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

### POST /api/logout
Revoke the current access token (invalidates the token).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (200 OK):**
```json
{
  "message": "Logged out successfully"
}
```

---

### GET /api/user
Retrieve the currently authenticated user's profile.

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john.doe@example.com",
  "timezone": "Asia/Kolkata",
  "created_at": "2025-11-12T10:00:00.000000Z",
  "updated_at": "2025-11-12T10:00:00.000000Z"
}
```

**Error Response (401 Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

### PATCH /api/user
### PUT /api/user
Update the authenticated user's profile (name, email, timezone, or password).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body (at least one field required):**
```json
{
  "name": "John Updated",
  "email": "john.updated@example.com",
  "timezone": "America/New_York",
  "password": "NewSecurePass123!",
  "password_confirmation": "NewSecurePass123!"
}
```

**Request Body (update only timezone):**
```json
{
  "timezone": "Europe/London"
}
```

**Request Body (update only name):**
```json
{
  "name": "Johnny Doe"
}
```

**Success Response (200 OK):**
```json
{
  "user": {
    "id": 1,
    "name": "John Updated",
    "email": "john.updated@example.com",
    "timezone": "America/New_York",
    "created_at": "2025-11-12T10:00:00.000000Z",
    "updated_at": "2025-11-12T15:30:00.000000Z"
  }
}
```

**Validation Rules:**
- `name`: optional, string, max:255
- `email`: optional, string, email, unique (ignores current user's email)
- `timezone`: optional, string, must be valid IANA timezone identifier
- `password`: optional, string, min:8, must have password_confirmation
- `password_confirmation`: required if password is provided
- **At least one field must be provided**

**Error Response (422 Unprocessable Entity - empty body):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "payload": ["At least one updatable field must be provided."]
  }
}
```

**Error Response (422 - email taken):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

---

### DELETE /api/user
Soft-delete the authenticated user's account.

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (200 OK):**
```json
{
  "message": "Account deleted successfully"
}
```

**Error Response (401 Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

## Categories

### GET /api/categories
List all categories for the authenticated user.

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "name": "Groceries",
      "icon": "cart"
    },
    {
      "id": 2,
      "user_id": 1,
      "name": "Entertainment",
      "icon": "movie"
    },
    {
      "id": 3,
      "user_id": 1,
      "name": "Transportation",
      "icon": "car"
    }
  ]
}
```

---

### POST /api/categories
Create a new category for the authenticated user.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "name": "Dining Out",
  "icon": "restaurant"
}
```

**Success Response (201 Created):**
```json
{
  "data": {
    "id": 4,
    "user_id": 1,
    "name": "Dining Out",
    "icon": "restaurant"
  }
}
```

**Validation Rules:**
- `name`: required, string, max:255, unique per user (two users can have same category name)
- `icon`: nullable, string

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name has already been taken."]
  }
}
```

**Note:** The category name must be unique per user. Different users can have categories with the same name.

---

### GET /api/categories/{id}
Get a specific category by ID (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "name": "Groceries",
    "icon": "cart"
  }
}
```

**Error Responses:**

**403 Forbidden:**
```json
{
  "message": "This action is unauthorized."
}
```

**404 Not Found:**
```json
{
  "message": "Resource not found."
}
```

---

### PUT /api/categories/{id}
### PATCH /api/categories/{id}
Update a specific category (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body (at least one field required):**
```json
{
  "name": "Supermarket",
  "icon": "shopping-bag"
}
```

**Success Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "name": "Supermarket",
    "icon": "shopping-bag"
  }
}
```

**Validation Rules:**
- `name`: sometimes, string, max:255, unique per user (ignores current category)
- `icon`: sometimes, nullable, string, max:255
- **At least one field must be provided** (name or icon)

**Error Response (422 Unprocessable Entity - empty body):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "payload": ["At least one updatable field must be provided."]
  }
}
```

---

### DELETE /api/categories/{id}
Soft-delete a category (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Success Response (204 No Content)**

**Error Response (403 Forbidden):**
```json
{
  "message": "This action is unauthorized."
}
```

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
      "created_at": "2025-11-04T10:00:00.000000Z",
      "updated_at": "2025-11-04T10:00:00.000000Z"
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
    "created_at": "2025-11-04T10:00:00.000000Z",
    "updated_at": "2025-11-04T10:00:00.000000Z"
  }
}

Validation:
- amount: required, numeric, min:0.01
- date: sometimes, date (optional)
- description: nullable, string
- category_id: nullable, exists:categories (must belong to user and not be soft-deleted)

---

### GET /api/transactions/{id}
Get a specific transaction by ID (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "amount": "45.99",
    "description": "Weekly groceries",
    "date": "2025-11-04",
    "created_at": "2025-11-04T10:00:00.000000Z",
    "updated_at": "2025-11-04T10:00:00.000000Z"
  }
}
```

**Error Response (403 Forbidden):**
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (404 Not Found):**
```json
{
  "message": "Resource not found."
}
```

---

### PUT /api/transactions/{id}
### PATCH /api/transactions/{id}
Update a specific transaction (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body (at least one field required):**
```json
{
  "amount": 52.50,
  "description": "Updated description - groceries and household items",
  "date": "2025-11-05",
  "category_id": 2
}
```

**Request Body (update only amount):**
```json
{
  "amount": 48.75
}
```

**Request Body (update only date):**
```json
{
  "date": "2025-11-06"
}
```

**Success Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 2,
    "amount": "52.50",
    "description": "Updated description - groceries and household items",
    "date": "2025-11-05",
    "created_at": "2025-11-04T10:00:00.000000Z",
    "updated_at": "2025-11-12T14:20:00.000000Z"
  }
}
```

**Validation Rules:**
- `amount`: optional, numeric, min:0.01
- `date`: optional, date format (YYYY-MM-DD)
- `description`: optional, nullable, string
- `category_id`: optional, nullable, must exist in categories, must belong to authenticated user, cannot be soft-deleted
- **At least one field must be provided**

**Error Response (422 - empty body):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "payload": ["At least one updatable field must be provided."]
  }
}
```

**Error Response (422 - invalid category):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "category_id": ["The selected category id is invalid."]
  }
}
```

**Manual Testing Examples:**

**Test 1: Update transaction amount**
```bash
curl -X PATCH http://localhost:8000/api/transactions/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "amount": 99.99
  }'
```

**Test 2: Update transaction date**
```bash
curl -X PATCH http://localhost:8000/api/transactions/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "date": "2025-11-15"
  }'
```

**Test 3: Try empty update (should fail with 422)**
```bash
curl -X PATCH http://localhost:8000/api/transactions/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{}'
```

---

### DELETE /api/transactions/{id}
Soft-delete a transaction (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (204 No Content)**

**Error Response (403 Forbidden):**
```json
{
  "message": "This action is unauthorized."
}
```

---

## Budgets

**Important Notes:**
- Budget dates are stored as DATE type (no time component)
- Budget calculations compare transaction dates (also DATE type) within the budget's date range
- Timezone handling for budgets is implicit through transaction dates
- Once a budget is created, its `category_id` **cannot be changed** (enforced for data integrity)

### GET /api/budgets
List all budgets for the authenticated user.

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "category_id": 1,
      "limit": "500.00",
      "period": "monthly",
      "start_date": "2025-11-01",
      "end_date": "2025-11-30",
      "created_at": "2025-11-01T08:00:00.000000Z",
      "updated_at": "2025-11-01T08:00:00.000000Z"
    },
    {
      "id": 2,
      "user_id": 1,
      "category_id": 2,
      "limit": "1200.00",
      "period": "yearly",
      "start_date": "2025-01-01",
      "end_date": "2025-12-31",
      "created_at": "2025-01-01T00:00:00.000000Z",
      "updated_at": "2025-01-01T00:00:00.000000Z"
    }
  ]
}

---

### POST /api/budgets
Create a new budget. If `end_date` is omitted, the server auto-computes it based on `period` and `start_date`:
- **weekly**: end_date = start_date + 1 week - 1 day
- **monthly**: end_date = start_date + 1 month - 1 day
- **yearly**: end_date = start_date + 1 year - 1 day

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body (with explicit end_date):**
```json
{
  "category_id": 1,
  "limit": 500.0,
  "period": "monthly",
  "start_date": "2025-11-01",
  "end_date": "2025-11-30"
}
```

**Request Body (end_date will be auto-computed):**
```json
{
  "category_id": 2,
  "limit": 1200.0,
  "period": "yearly",
  "start_date": "2025-01-01"
}
```

**Request Body (weekly budget):**
```json
{
  "category_id": 3,
  "limit": 100.0,
  "period": "weekly",
  "start_date": "2025-11-11"
}
```

**Success Response (201 Created):**
```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "limit": "500.00",
    "period": "monthly",
    "start_date": "2025-11-01",
    "end_date": "2025-11-30",
    "created_at": "2025-11-01T08:00:00.000000Z",
    "updated_at": "2025-11-01T08:00:00.000000Z",
    "progress_stats": {
      "limit": 500.0,
      "spent": 145.5,
      "remaining": 354.5,
      "progress_percent": 29.1,
      "is_over_budget": false
    }
  }
}
```

**Validation Rules:**
- `limit` (or `amount`): required, numeric, min:0.01
- `period`: required, must be one of: "weekly", "monthly", "yearly"
- `start_date`: required, date format (YYYY-MM-DD)
- `end_date`: optional, date format, must be >= start_date. Auto-computed if omitted
- `category_id`: required, must exist in categories, must belong to authenticated user, cannot be soft-deleted

**Error Response (422 - category doesn't belong to user):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "category_id": ["The selected category id is invalid."]
  }
}
```

**Manual Testing Examples:**

**Test 1: Create monthly budget with auto-computed end_date**
```bash
curl -X POST http://localhost:8000/api/budgets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "category_id": 1,
    "limit": 750.00,
    "period": "monthly",
    "start_date": "2025-12-01"
  }'
```

**Test 2: Create yearly budget**
```bash
curl -X POST http://localhost:8000/api/budgets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "category_id": 2,
    "limit": 5000.00,
    "period": "yearly",
    "start_date": "2026-01-01",
    "end_date": "2026-12-31"
  }'
```

---

### GET /api/budgets/{id}
Get a specific budget by ID with progress stats (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "limit": "500.00",
    "period": "monthly",
    "start_date": "2025-11-01",
    "end_date": "2025-11-30",
    "created_at": "2025-11-01T08:00:00.000000Z",
    "updated_at": "2025-11-01T08:00:00.000000Z",
    "progress_stats": {
      "limit": 500.0,
      "spent": 345.75,
      "remaining": 154.25,
      "progress_percent": 69.15,
      "is_over_budget": false
    }
  }
}
```

**Progress Stats Explanation:**
- `limit`: The budget limit amount
- `spent`: Total amount spent in transactions within the budget's date range and category
- `remaining`: limit - spent (minimum 0)
- `progress_percent`: (spent / limit) * 100, rounded to 2 decimals
- `is_over_budget`: true if spent > limit

**Error Response (403 Forbidden):**
```json
{
  "message": "This action is unauthorized."
}
```

---

### PUT /api/budgets/{id}
### PATCH /api/budgets/{id}
Update a specific budget (must belong to the authenticated user).

**IMPORTANT:** The `category_id` field **cannot be changed** after budget creation. This is enforced for data integrity.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body (at least one field required):**
```json
{
  "limit": 750.0,
  "period": "yearly",
  "start_date": "2025-11-01",
  "end_date": "2026-10-31"
}
```

**Request Body (update only limit):**
```json
{
  "limit": 600.00
}
```

**Request Body (update period and let end_date auto-compute):**
```json
{
  "period": "monthly",
  "start_date": "2025-12-01"
}
```

**Success Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "limit": "750.00",
    "period": "yearly",
    "start_date": "2025-11-01",
    "end_date": "2026-10-31",
    "created_at": "2025-11-01T08:00:00.000000Z",
    "updated_at": "2025-11-12T16:45:00.000000Z",
    "progress_stats": {
      "limit": 750.0,
      "spent": 345.75,
      "remaining": 404.25,
      "progress_percent": 46.1,
      "is_over_budget": false
    }
  }
}
```

**Validation Rules:**
- `limit` (or `amount`): optional, numeric, min:0.01
- `period`: optional, must be one of: "weekly", "monthly", "yearly"
- `start_date`: optional, date format (YYYY-MM-DD)
- `end_date`: optional, date format, must be >= start_date
- `category_id`: **PROHIBITED** - cannot be changed after budget creation
- **At least one updatable field must be provided** (limit, period, start_date, or end_date)

**Error Response (422 - empty body):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "payload": ["At least one updatable field must be provided."]
  }
}
```

**Error Response (422 - trying to change category):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "category_id": ["The category id field is prohibited."]
  }
}
```

**Manual Testing Examples:**

**Test 1: Update budget limit**
```bash
curl -X PATCH http://localhost:8000/api/budgets/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "limit": 850.00
  }'
```

**Test 2: Try to change category (should fail)**
```bash
curl -X PATCH http://localhost:8000/api/budgets/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "category_id": 5
  }'
```

**Test 3: Try empty update (should fail)**
```bash
curl -X PATCH http://localhost:8000/api/budgets/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{}'
```

---

### DELETE /api/budgets/{id}
Delete a budget (must belong to the authenticated user).

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (204 No Content)**

**Error Response (403 Forbidden):**
```json
{
  "message": "This action is unauthorized."
}
```

---

---

## Errors

**Error Response Format:**

All error responses follow a consistent format with appropriate HTTP status codes.

**401 Unauthorized:**
```json
{
  "message": "Unauthenticated."
}
```

**403 Forbidden:**
```json
{
  "message": "This action is unauthorized."
}
```

**404 Not Found:**
```json
{
  "message": "Resource not found."
}
```

**422 Validation Error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "amount": ["The amount must be at least 0.01."],
    "payload": ["At least one updatable field must be provided."]
  }
}
```

**500 Server Error:**
```json
{
  "message": "Server Error"
}
```

---

## Important Notes

### Date and Timezone Handling

**Transaction Dates:**
- Transaction dates use a three-level fallback for timezone resolution:
  1. **User's profile timezone** (set via `PATCH /api/user`)
  2. **X-Timezone request header** (e.g., `X-Timezone: Asia/Kolkata`)
  3. **Server default timezone** (UTC from config)
- When `date` is omitted in transaction creation, the server defaults to the current date in the resolved timezone
- All dates are stored as DATE type (no time component), making them timezone-safe

**Budget Calculations:**
- Budget dates (`start_date`, `end_date`) are DATE type with no time component
- Budget progress calculations use `whereDate()` to compare transaction dates within the budget's date range
- Since all dates are stored without time components, timezone issues are eliminated
- The `spent` amount includes all transactions matching the budget's category within the date range

**Best Practices:**
1. Set user timezone during registration or via profile update for consistent date handling
2. Use explicit dates in transaction requests for past/future transactions
3. Budget date ranges should align with natural periods (month boundaries, year boundaries)

### Security and Validation

**Empty Update Bodies:**
- All update endpoints (PATCH/PUT) require at least one updatable field
- Empty request bodies return 422 validation error with message: "At least one updatable field must be provided."

**Category Ownership:**
- Categories must belong to the authenticated user
- Cannot assign transactions/budgets to categories owned by other users
- Soft-deleted categories cannot be used for new transactions or budgets

**Budget Category Immutability:**
- Once a budget is created, its `category_id` **cannot be changed**
- This prevents data integrity issues with historical budget calculations
- To change a budget's category, delete the old budget and create a new one

**Category Name Uniqueness:**
- Category names must be unique **per user** (not globally)
- Different users can have categories with the same name
- Update validation ignores the current category when checking uniqueness

### Token Management

- Tokens expire after 60 minutes by default (configurable via `config/token.php`)
- `expires_at`: ISO8601 timestamp indicating when the token expires
- `expires_in`: Number of seconds until token expiration
- Use the `POST /api/refresh` endpoint to rotate tokens before expiration
- Login revokes all existing tokens for the user
- Logout revokes only the current token

### Request Format

- All request bodies must be raw JSON (no multipart/form-data)
- Use `Content-Type: application/json` header
- Use `Accept: application/json` header
- Protected endpoints require `Authorization: Bearer {token}` header
- Monetary values are returned as strings for precision (e.g., `"45.99"`)
- Dates use ISO 8601 format (`YYYY-MM-DD`)
- Timestamps use ISO 8601 with microseconds and timezone (`2025-11-04T10:00:00.000000Z`)

---

## Notes

- All request bodies must be raw JSON (no form-data).
- Use `Authorization: Bearer {token}` for protected endpoints.
- Tokens expire after 60 minutes by default; `expires_at` and `expires_in` are returned by auth endpoints.
- Transactions accept an optional `X-Timezone` header; if `date` is omitted, the server uses “today” in that timezone.