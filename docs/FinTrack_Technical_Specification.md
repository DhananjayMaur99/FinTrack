# FinTrack – Technical Specification (Updated Nov 2025)

## 1.0 Introduction

### 1.1 Project Purpose

**FinTrack** is a RESTful API backend for a personal expense logging application.  
The project's goal is to provide users with a secure, simple, and detailed mechanism to **log**, **categorize**, and **analyze** their spending habits.

The platform answers one key question:

> **“Where is my money going?”**

It provides foundational data services for expenditure tracking and budget management.

---

### 1.2 Core Use Cases & User Journey

1. Onboarding
  - Register with name, email, password; receive API token (Sanctum).
  - Login regenerates token (old tokens revoked).
2. Categorization
  - Create custom categories for personalized tracking.
3. Transaction Logging
  - Record expenses (amount, date, optional category, description).
  - View/paginate personal transaction history.
4. Budgeting
  - Create budgets (monthly/yearly) scoped optionally to a category or overall spending.
  - Monitor progress stats (spent, remaining, percent, over-budget flag).
5. Account Management
  - Logout (token revoked), soft-delete account (history retained), retrieve profile.

Primary KPIs: monthly spending variance, category distribution, budget adherence percentage.

---

## 2.0 System Architecture

### 2.1 Technology Stack

| Component | Technology |
|------------|-------------|
| **Framework** | Laravel Framework ^12 (current composer constraint) |
| **Database** | MySQL |
| **Authentication** | Laravel Sanctum (Token-based API Authentication) |

---

### 2.2 Architectural Principles

The API follows a **modular, reusable, and decoupled N-tier architecture**, aligned with Laravel best practices.

#### Layers

- **Controller Layer (`app/Http/Controllers/`)**
  - REST endpoints, authorizes actions via Policies.
  - Mostly delegates complex logic (e.g., budget progress) to Services.

- **Validation Layer (`app/Http/Requests/`)**
  - Handles all data validation using Form Request classes.
  - Keeps validation reusable and separated from controllers.

- **Service Layer (`app/Services/`)**
  - Currently only `BudgetService` encapsulates budget lifecycle & progress computation.
  - Future: introduce `ReportingService` for aggregations (category breakdown, trending).

- **Data Access Layer (`app/Models/`)**
  - Handles only database-level concerns:
    - Relationships (`hasMany`, `belongsTo`)
    - Attribute casting
    - Query scopes

---

## 3.0 Database Schema & Design

The database follows **Third Normal Form (3NF)** for maximum integrity and minimal redundancy.  
Foreign key constraints ensure consistent linkage between entities.

---

### 3.1 Entity: `users`

| Column | Type | Constraints | Description |
|---------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique identifier for the user |
| name | string | Not Null | Full name |
| email | string | Not Null, Unique | Login email |
| password | string | Not Null | Hashed password |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |
| deleted_at | timestamp | Nullable | Soft-deletes flag |

---

### 3.2 Entity: `categories`

| Column | Type | Constraints | Description |
|---------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique category ID |
| user_id | bigint | FK → users.id | Owner of the category |
| name | string | Not Null | Display name (e.g., “Groceries”) |
| icon | string | Nullable | Optional icon reference |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |
| deleted_at | timestamp | Nullable | Soft-deletes flag |

---

### 3.3 Entity: `transactions`

| Column | Type | Constraints | Description |
|---------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique transaction ID |
| user_id | bigint | FK → users.id | Owner of the transaction |
| category_id | bigint | FK → categories.id | Category (required in schema; API treats nullable logically) |
| amount | decimal(10,2) | Not Null | Expense value |
| description | string | Nullable | Optional note |
| date | date | Not Null | Transaction date |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |
| deleted_at | timestamp | Nullable | Soft-deletes flag |

---

### 3.4 Entity: `budgets`

| Column | Type | Constraints | Description |
|---------|------|-------------|--------------|
| id | bigint | PK, Unsigned, Auto-Inc | Unique budget ID |
| user_id | bigint | FK → users.id | Owner of the budget |
| category_id | bigint | FK → categories.id, Nullable | Target category (optional) |
| limit | decimal(10,2) | Not Null | Spending limit |
| period | enum('monthly', 'yearly') | Not Null | Frequency of the budget |
| start_date | date | Not Null | Budget start date |
| end_date | date | Nullable | Optional expiration date |
| created_at | timestamp | Nullable | Record creation time |
| updated_at | timestamp | Nullable | Last record update |

---

### 3.5 Entity-Relationship Model (Cardinality)

| Relationship | Type | Description |
|---------------|------|-------------|
| User → Category | 1:N | One user can own many categories |
| User → Transaction | 1:N | One user can own many transactions |
| User → Budget | 1:N | One user can own many budgets |
| Category → Transaction | 1:N | One category can apply to many transactions |
| Category → Budget | 1:N | One category can target many budgets |

---

## 4.0 API Endpoints Overview

All API routes (except auth) require `Authorization: Bearer <token>` using Sanctum.

| Method | Path | Controller@Action | Auth | Description |
|--------|------|-------------------|------|-------------|
| POST | /api/register | AuthController@register | No | Create user & return token |
| POST | /api/login | AuthController@login | No | Authenticate & issue token (revokes old) |
| POST | /api/logout | AuthController@logout | Yes | Revoke current token |
| GET | /api/user | Closure | Yes | Return authenticated user profile |
| DELETE | /api/user | AuthController@destroy | Yes | Soft-delete user & revoke tokens |
| GET | /api/categories | CategoryController@index | Yes | List user categories |
| POST | /api/categories | CategoryController@store | Yes | Create category |
| GET | /api/categories/{id} | CategoryController@show | Yes + Policy | View category if owner |
| PATCH/PUT | /api/categories/{id} | CategoryController@update | Yes + Policy | Update category |
| DELETE | /api/categories/{id} | CategoryController@destroy | Yes + Policy | Soft-delete category |
| GET | /api/transactions | TransactionController@index | Yes | Paginated user transactions |
| POST | /api/transactions | TransactionController@store | Yes + Policy(create) | Create transaction |
| GET | /api/transactions/{id} | TransactionController@show | Yes + Policy(view) | View transaction |
| PATCH/PUT | /api/transactions/{id} | TransactionController@update | Yes + Policy(update) | Update transaction |
| DELETE | /api/transactions/{id} | TransactionController@destroy | Yes + Policy(delete) | Soft-delete transaction |
| GET | /api/budgets | BudgetController@index | Yes | List budgets (future: paginate) |
| POST | /api/budgets | BudgetController@store | Yes + Policy(create) | Create budget |
| GET | /api/budgets/{id} | BudgetController@show | Yes + Policy(view) | View budget + progress stats |
| PATCH/PUT | /api/budgets/{id} | BudgetController@update | Yes + Policy(update) | Update budget |
| DELETE | /api/budgets/{id} | BudgetController@destroy | Yes + Policy(delete) | Delete budget |

Notes:
1. `index` for budgets currently returns relation object; enhancement: apply pagination and transform resources.
2. Budgets compute dynamic progress using `BudgetService::getBudgetProgress()`.
3. Transactions & Categories leverage soft deletes; budgets do NOT (design decision to retain immutable budget history).

### 4.1 Validation Rules Summary

| Request | Key Fields | Rules (simplified) |
|---------|------------|--------------------|
| RegisterUserRequest | name,email,password | required, unique email, password confirmed min:8 |
| LoginUserRequest | email,password | required email format, required password |
| CategoryStoreRequest | name,icon | name required string <=255, icon nullable string |
| CategoryUpdateRequest | name,icon | both sometimes, string, icon nullable |
| TransactionStoreRequest | amount,date,description,category_id | amount numeric min 0.01; date valid; category_id nullable exists & owned & not deleted |
| TransactionUpdateRequest | amount,date,description,category_id | same as store but all sometimes |
| BudgetStoreRequest | limit,period,start_date,end_date,category_id | limit numeric min 0.01; period in monthly|yearly; dates; category_id nullable exists & owned & not deleted |
| BudgetUpdateRequest | limit,period,start_date,end_date,category_id | same as store but all sometimes |

### 4.2 Authorization Model

Policies: `CategoryPolicy`, `TransactionPolicy`, `BudgetPolicy` mapped in `AuthServiceProvider`.

| Policy | view | create | update | delete |
|--------|------|--------|--------|--------|
| Category | owner only | any auth | owner only | owner only |
| Transaction | owner only | any auth | owner only | owner only |
| Budget | owner only | any auth | owner only | owner only |

Design Choices:
- `viewAny` allowed; controllers still scope queries by `user()`.
- Soft deletes on User, Category, Transaction preserve history; budgets remain hard-deleted.
- When a Category is deleted, related Budgets auto set `category_id = NULL` (overall budget fallback).

### 4.3 Authentication & Tokens

- Sanctum personal access tokens with per-token `expires_at`.
- Current TTL: 60 minutes (configurable via `config/token.php` and `TOKEN_TTL_MINUTES`).
- Login revokes previous tokens. Logout deletes current token. Delete account revokes all tokens then soft-deletes user.
- Refresh endpoint (`POST /api/refresh`) rotates the token and returns new expiry metadata.

### 4.4 Transaction Date Handling

- Users may omit a transaction date; the server defaults it to "today" in the user's timezone.
- The user's timezone comes from `users.timezone` (or `X-Timezone` header, fallback to `config('app.timezone')`).
- We persist:
  - `date` (legacy local date) to keep compatibility with budget queries.
  - `date_local` (explicit local date) for clarity.
  - `occurred_at_utc` (UTC timestamp) for precise ordering and cross-timezone reporting.

### 4.5 Budget Progress Computation

`BudgetService::getBudgetProgress(budget)`:
- Filters transactions by user, date range (`start_date` to `end_date` or now), and optional category.
- Aggregates `spent = sum(amount)`.
- Computes:
  - remaining = limit - spent
  - progress_percent = (spent / limit) * 100 (rounded 2 decimals)
  - is_over_budget = spent > limit

Returned via `BudgetResource` as `progress_stats`.

### 4.6 Error Handling & Response Format

Errors follow Laravel default JSON validation structure:
```json
{
  "message": "The given data was invalid.",
  "errors": {"field": ["Rule message..."]}
}
```
Domain responses use Resource classes for consistent shape. Missing resources or authorization failures return 404/403 respectively.

### 4.7 Performance & Scalability Notes

- Index endpoints should add pagination for categories and budgets (transactions already paginated).
- Add composite indexes (user_id,date) on `transactions` for large datasets.
- Future caching layer for budget progress (heavy recomputation avoided with event-driven invalidation).

### 4.8 Security Enhancements Implemented

- Strict per-user scoping in controllers (`$request->user()->relation()`).
- Form Requests exclude `user_id` to prevent mass-assignment privilege escalation.
- Foreign keys with cascade / set null maintain referential integrity.
- Soft-deleted categories still load in transaction history using `withTrashed()` (prevents broken historical views).

### 4.9 Known Gaps / Backlog

| Item | Description | Suggested Priority |
|------|-------------|--------------------|
| Reporting endpoints | Category spend summary, monthly trend | High |
| Pagination for budgets & categories | Avoid large in-memory collections | High |
| Rate limiting | Protect auth endpoints from brute force | Medium |
| Indexes | Add index (user_id, date) on transactions; (user_id, category_id) on budgets | Medium |
| Soft delete for budgets | Consider historical recovery; evaluate business need | Low |
| OpenAPI/Swagger spec | Machine-readable API definition | Medium |
| Test coverage expansion | Add feature + policy tests | High |

## 5.0 Non-Functional Requirements

### Security
- Sanctum bearer tokens; single active token per login session (previous revoked).
- Policies enforce per-resource ownership.
- Validation forbids referencing soft-deleted categories.

### Data Integrity
- Strict foreign key constraints & cascading rules.
- Monetary decimals use `DECIMAL(10,2)`; casting in models ensures consistent serialization.

### Maintainability
- Separation of concerns (Requests, Controllers, Services, Resources, Policies).
- Future service extraction planned for reporting logic.

### Observability (Planned)
- Introduce structured logging (request ID correlation).
- Add metrics: request latency, budget computation time.

## 6.0 Sequence Diagram (Budget Retrieval)

```text
Client -> API (GET /api/budgets/{id})
API -> Auth: Validate Bearer token
API -> DB: Load Budget
API -> Policy: authorize(view, budget)
API -> BudgetService: getBudgetProgress(budget)
BudgetService -> DB: Query Transactions (user_id + date range [+ category_id])
DB --> BudgetService: sum(amount)
BudgetService --> API: stats array
API -> BudgetResource: merge base + progress_stats
API --> Client: 200 JSON budget + stats
```

## 7.0 Glossary

| Term | Definition |
|------|------------|
| Budget | Spending constraint (limit + period + date range) optionally tied to a category. |
| Transaction | A single expense entry (amount, date, description, category). |
| Category | User-defined expense grouping label. |
| Progress Stats | Derived metrics describing budget performance (spent, remaining, percent). |
| Soft Delete | Logical deletion preserving record for historical queries (uses `deleted_at`). |

---

Document maintained by automated assistant. Last updated: 2025-11-10.

---

**End of Document**
