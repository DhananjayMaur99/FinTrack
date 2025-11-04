# FinTrack API Endpoints Documentation

## Authentication Endpoints

### POST /api/register

Register a new user account.

**Request Body:**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response (201 Created):**

```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "created_at": "2025-11-04T10:00:00.000000Z",
        "updated_at": "2025-11-04T10:00:00.000000Z"
    },
    "token": "1|abc123..."
}
```

**Validation:**

-   name: required, string, max:255
-   email: required, email, unique:users
-   password: required, min:8, confirmed

---

### POST /api/login

Authenticate and receive access token.

**Request Body:**

```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (200 OK):**

```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    },
    "token": "2|xyz789..."
}
```

**Errors:**

-   401: Invalid credentials

---

### POST /api/logout

Revoke current access token.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

```json
{
    "message": "Logged out successfully"
}
```

---

## Category Endpoints

### GET /api/categories

List all categories for authenticated user.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

```json
{
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "name": "Groceries",
            "icon": "cart",
            "created_at": "2025-11-04T10:00:00.000000Z",
            "updated_at": "2025-11-04T10:00:00.000000Z"
        }
    ]
}
```

---

### POST /api/categories

Create a new category.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "name": "Entertainment",
    "icon": "movie"
}
```

**Response (201 Created):**

```json
{
    "data": {
        "id": 2,
        "user_id": 1,
        "name": "Entertainment",
        "icon": "movie",
        "created_at": "2025-11-04T10:00:00.000000Z",
        "updated_at": "2025-11-04T10:00:00.000000Z"
    }
}
```

**Validation:**

-   name: required, string, max:255
-   icon: nullable, string, max:255

---

### GET /api/categories/{id}

Get a specific category.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "user_id": 1,
        "name": "Groceries",
        "icon": "cart",
        "created_at": "2025-11-04T10:00:00.000000Z",
        "updated_at": "2025-11-04T10:00:00.000000Z"
    }
}
```

**Errors:**

-   403: Not authorized (category belongs to another user)
-   404: Category not found

---

### PUT /api/categories/{id}

Update a category.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "name": "Supermarket",
    "icon": "shopping-bag"
}
```

**Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "user_id": 1,
        "name": "Supermarket",
        "icon": "shopping-bag",
        "created_at": "2025-11-04T10:00:00.000000Z",
        "updated_at": "2025-11-04T11:00:00.000000Z"
    }
}
```

**Validation:**

-   name: sometimes, string, max:255
-   icon: sometimes, nullable, string, max:255

---

### DELETE /api/categories/{id}

Soft delete a category.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (204 No Content)**

**Errors:**

-   403: Not authorized

---

## Transaction Endpoints

### GET /api/transactions

List all transactions for authenticated user (paginated).

**Headers:**

```
Authorization: Bearer {token}
```

**Query Parameters:**

-   page: integer (default: 1)
-   per_page: integer (default: 15)

**Response (200 OK):**

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "category_id": 1,
      "amount": "45.99",
      "description": "Weekly groceries",
      "date": "2025-11-04T00:00:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

### POST /api/transactions

Create a new transaction.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "category_id": 1,
    "amount": 45.99,
    "description": "Weekly groceries",
    "date": "2025-11-04"
}
```

**Response (201 Created):**

```json
{
    "data": {
        "id": 1,
        "user_id": 1,
        "category_id": 1,
        "amount": "45.99",
        "description": "Weekly groceries",
        "date": "2025-11-04T00:00:00.000000Z"
    }
}
```

**Validation:**

-   amount: required, numeric, min:0.01
-   date: required, date
-   description: nullable, string
-   category_id: nullable, exists:categories (must belong to user)

---

### GET /api/transactions/{id}

Get a specific transaction.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "user_id": 1,
        "category_id": 1,
        "amount": "45.99",
        "description": "Weekly groceries",
        "date": "2025-11-04T00:00:00.000000Z"
    }
}
```

---

### PUT /api/transactions/{id}

Update a transaction.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "amount": 52.5,
    "description": "Updated description"
}
```

**Response (200 OK):**

```json
{
    "data": {
        "id": 1,
        "user_id": 1,
        "category_id": 1,
        "amount": "52.50",
        "description": "Updated description",
        "date": "2025-11-04T00:00:00.000000Z"
    }
}
```

**Validation:**

-   amount: sometimes, numeric, min:0.01
-   date: sometimes, date
-   description: sometimes, nullable, string
-   category_id: sometimes, nullable, exists:categories (must belong to user)

---

### DELETE /api/transactions/{id}

Soft delete a transaction.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (204 No Content)**

---

## Budget Endpoints

### GET /api/budgets

List all budgets for authenticated user (paginated).

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

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
            "end_date": "2025-11-30"
        }
    ]
}
```

---

### POST /api/budgets

Create a new budget.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "category_id": 1,
    "limit": 500.0,
    "period": "monthly",
    "start_date": "2025-11-01",
    "end_date": "2025-11-30"
}
```

**Response (201 Created):**

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

**Validation:**

-   limit: required, numeric, min:0.01
-   period: required, in:monthly,yearly
-   start_date: required, date
-   end_date: nullable, date, after_or_equal:start_date
-   category_id: nullable, exists:categories (must belong to user)

---

### GET /api/budgets/{id}

Get a specific budget with progress stats.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

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

---

### PUT /api/budgets/{id}

Update a budget.

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "limit": 750.0,
    "period": "yearly"
}
```

**Response (200 OK):**

```json
{
  "data": {
    "id": 1,
    "user_id": 1,
    "category_id": 1,
    "limit": "750.00",
    "period": "yearly",
    "start_date": "2025-11-01",
    "end_date": "2025-11-30",
    "progress_stats": {...}
  }
}
```

**Validation:**

-   limit: sometimes, numeric, min:0.01
-   period: sometimes, in:monthly,yearly
-   start_date: sometimes, date
-   end_date: sometimes, nullable, date, after_or_equal:start_date
-   category_id: sometimes, nullable, exists:categories (must belong to user)

---

### DELETE /api/budgets/{id}

Delete a budget.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (204 No Content)**

---

## User Endpoint

### GET /api/user

Get currently authenticated user.

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200 OK):**

```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": null,
    "created_at": "2025-11-04T10:00:00.000000Z",
    "updated_at": "2025-11-04T10:00:00.000000Z"
}
```

---

## Error Responses

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 403 Forbidden

```json
{
    "message": "This action is unauthorized."
}
```

### 404 Not Found

```json
{
    "message": "Resource not found."
}
```

### 422 Validation Error

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "amount": ["The amount must be at least 0.01."]
    }
}
```

### 500 Server Error

```json
{
    "message": "Server Error"
}
```

---

## Notes

-   All timestamps are in UTC ISO 8601 format
-   Pagination follows Laravel conventions (links, meta)
-   Decimal values returned as strings to preserve precision
-   All protected endpoints require valid Sanctum token
-   Token expires based on Sanctum configuration
