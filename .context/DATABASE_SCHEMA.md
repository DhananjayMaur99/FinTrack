# FinTrack Database Schema

## Entity Relationship Diagram

```
users (1) ──────< (N) categories
users (1) ──────< (N) transactions
users (1) ──────< (N) budgets
categories (1) ──< (N) transactions
categories (1) ──< (N) budgets
```

## Tables

### users

Primary entity for authentication and ownership.

| Column            | Type         | Constraints      | Description             |
| ----------------- | ------------ | ---------------- | ----------------------- |
| id                | bigint       | PK, Auto-Inc     | Unique user ID          |
| name              | varchar(255) | Not Null         | User's full name        |
| email             | varchar(255) | Unique, Not Null | Login email             |
| email_verified_at | timestamp    | Nullable         | Email verification time |
| password          | varchar(255) | Not Null         | Hashed password         |
| remember_token    | varchar(100) | Nullable         | Session token           |
| created_at        | timestamp    | Nullable         | Account creation        |
| updated_at        | timestamp    | Nullable         | Last update             |

**Relationships:**

-   Has many: categories, transactions, budgets

---

### categories

User-defined spending/income categories.

| Column     | Type         | Constraints             | Description        |
| ---------- | ------------ | ----------------------- | ------------------ |
| id         | bigint       | PK, Auto-Inc            | Unique category ID |
| user_id    | bigint       | FK → users.id, Not Null | Owner              |
| name       | varchar(255) | Not Null                | Category name      |
| icon       | varchar(255) | Nullable                | Icon identifier    |
| created_at | timestamp    | Nullable                | Created timestamp  |
| updated_at | timestamp    | Nullable                | Updated timestamp  |
| deleted_at | timestamp    | Nullable                | Soft delete flag   |

**Indexes:**

-   user_id (foreign key)

**Relationships:**

-   Belongs to: user
-   Has many: transactions, budgets

**Soft Deletes**: Yes

---

### transactions

Individual income/expense records.

| Column      | Type          | Constraints                  | Description           |
| ----------- | ------------- | ---------------------------- | --------------------- |
| id          | bigint        | PK, Auto-Inc                 | Unique transaction ID |
| user_id     | bigint        | FK → users.id, Not Null      | Owner                 |
| category_id | bigint        | FK → categories.id, Nullable | Category (optional)   |
| amount      | decimal(10,2) | Not Null                     | Transaction amount    |
| description | text          | Nullable                     | Transaction details   |
| date        | date          | Not Null                     | Transaction date      |
| created_at  | timestamp     | Nullable                     | Record created        |
| updated_at  | timestamp     | Nullable                     | Record updated        |
| deleted_at  | timestamp     | Nullable                     | Soft delete flag      |

**Indexes:**

-   user_id (foreign key)
-   category_id (foreign key)
-   date (for range queries)

**Relationships:**

-   Belongs to: user, category

**Soft Deletes**: Yes

**Business Rules:**

-   Amount must be ≥ 0.01
-   Category must belong to same user (if provided)
-   Date can be past, present, or future

---

### budgets

Spending limit tracking with progress calculation.

| Column      | Type                      | Constraints                  | Description                |
| ----------- | ------------------------- | ---------------------------- | -------------------------- |
| id          | bigint                    | PK, Auto-Inc                 | Unique budget ID           |
| user_id     | bigint                    | FK → users.id, Not Null      | Owner                      |
| category_id | bigint                    | FK → categories.id, Nullable | Target category (optional) |
| limit       | decimal(10,2)             | Not Null                     | Spending limit             |
| period      | enum('monthly', 'yearly') | Not Null                     | Budget frequency           |
| start_date  | date                      | Not Null                     | Budget start               |
| end_date    | date                      | Nullable                     | Budget end (optional)      |
| created_at  | timestamp                 | Nullable                     | Record created             |
| updated_at  | timestamp                 | Nullable                     | Record updated             |

**Indexes:**

-   user_id (foreign key)
-   category_id (foreign key)
-   start_date, end_date (for range queries)

**Relationships:**

-   Belongs to: user, category

**Soft Deletes**: No

**Business Rules:**

-   Limit must be ≥ 0.01
-   Category must belong to same user (if provided)
-   If category_id is null, budget applies to all spending
-   end_date must be ≥ start_date (if provided)

---

### personal_access_tokens

Sanctum authentication tokens.

| Column         | Type         | Constraints      | Description       |
| -------------- | ------------ | ---------------- | ----------------- |
| id             | bigint       | PK, Auto-Inc     | Token ID          |
| tokenable_type | varchar(255) | Not Null         | Polymorphic type  |
| tokenable_id   | bigint       | Not Null         | User ID           |
| name           | varchar(255) | Not Null         | Token name        |
| token          | varchar(64)  | Unique, Not Null | Hashed token      |
| abilities      | text         | Nullable         | Token permissions |
| last_used_at   | timestamp    | Nullable         | Last usage time   |
| expires_at     | timestamp    | Nullable         | Expiration time   |
| created_at     | timestamp    | Nullable         | Token issued      |
| updated_at     | timestamp    | Nullable         | Token updated     |

**Indexes:**

-   tokenable (type + id)
-   token (unique)

---

## Data Integrity Rules

1. **Foreign Key Constraints**: All enabled
2. **Cascading Deletes**: Not implemented (soft deletes preferred)
3. **Decimal Precision**: DECIMAL(10,2) for all monetary values
4. **Date Ranges**: Validated in application layer
5. **User Ownership**: Enforced via policies and scoped queries

## Migration Files

-   `2025_11_03_105400_create_users_table.php`
-   `2025_11_03_105401_create_categories_table.php`
-   `2025_11_03_105402_create_transactions_table.php`
-   `2025_11_03_105403_create_budgets_table.php`
-   `2025_11_03_115327_create_personal_access_tokens_table.php`

## Seeders

-   `DatabaseSeeder.php`: Main seeder orchestrator
-   User factories, category factories, transaction factories, budget factories

## Notes

-   All timestamps use Laravel's automatic management
-   Soft deletes enable "trash" functionality
-   UUID not used (standard auto-increment IDs)
-   No explicit audit trail tables (rely on timestamps)
