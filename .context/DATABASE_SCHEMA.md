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

| Column     | Type         | Constraints      | Description      |
| ---------- | ------------ | ---------------- | ---------------- |
| id         | bigint       | PK, Auto-Inc     | Unique user ID   |
| name       | varchar(255) | Not Null         | User's full name |
| email      | varchar(255) | Unique, Not Null | Login email      |
| password   | varchar(255) | Not Null         | Hashed password  |
| created_at | timestamp    | Nullable         | Account creation |
| updated_at | timestamp    | Nullable         | Last update      |
| deleted_at | timestamp    | Nullable         | Soft delete flag |

**Relationships:**

-   Has many: categories, transactions, budgets

**Soft Deletes**: Yes

**Note**: The users table does NOT include `email_verified_at` or `remember_token` columns in the current migration.

---

### categories

User-defined spending/income categories.

| Column     | Type         | Constraints             | Description        |
| ---------- | ------------ | ----------------------- | ------------------ |
| id         | bigint       | PK, Auto-Inc            | Unique category ID |
| name       | varchar(255) | Not Null                | Category name      |
| user_id    | bigint       | FK → users.id, Not Null | Owner              |
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

**Note**: Column order in migration is: id, name, user_id, icon, timestamps, softDeletes.

---

### transactions

Individual income/expense records.

| Column      | Type          | Constraints             | Description           |
| ----------- | ------------- | ----------------------- | --------------------- |
| id          | bigint        | PK, Auto-Inc            | Unique transaction ID |
| user_id     | bigint        | FK → users.id, Not Null | Owner                 |
| category_id | bigint        | FK → categories.id      | Category (required)   |
| amount      | decimal(10,2) | Not Null                | Transaction amount    |
| description | varchar(255)  | Nullable                | Transaction details   |
| date        | date          | Not Null                | Transaction date      |
| created_at  | timestamp     | Nullable                | Record created        |
| updated_at  | timestamp     | Nullable                | Record updated        |
| deleted_at  | timestamp     | Nullable                | Soft delete flag      |

**Indexes:**

-   user_id (foreign key)
-   category_id (foreign key)
-   date (for range queries)

**Relationships:**

-   Belongs to: user, category

**Soft Deletes**: Yes

**Business Rules:**

-   Amount must be ≥ 0.01
-   Category must belong to same user
-   Date can be past, present, or future

**Note**: In the migration, `category_id` is NOT nullable, but the model's `$fillable` allows it. The migration should be updated to match business logic if NULL categories are needed.

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

**Important Note**: The Budget model does NOT use SoftDeletes trait. The migration does not include a `deleted_at` column.

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

1. **Foreign Key Constraints**: Defined but not enforced with ON DELETE/ON UPDATE clauses
2. **Cascading Deletes**: Not implemented (soft deletes preferred for categories and transactions)
3. **Decimal Precision**: DECIMAL(10,2) for all monetary values
4. **Date Ranges**: Validated in application layer
5. **User Ownership**: Enforced via policies and scoped queries

## Model Summary

| Model       | Soft Deletes | Fillable                                                  | Casts                                   |
| ----------- | ------------ | --------------------------------------------------------- | --------------------------------------- |
| User        | ✅ Yes       | name, email, password                                     | email_verified_at, password (hashed)    |
| Category    | ✅ Yes       | user_id, name, icon                                       | id, user_id (integer)                   |
| Transaction | ✅ Yes       | user_id, category_id, amount, description, date           | amount (decimal:2), date                |
| Budget      | ❌ No        | user_id, category_id, limit, period, start_date, end_date | limit (decimal:2), start_date, end_date |

## Migration Files

-   `2025_11_03_105400_create_users_table.php`
-   `2025_11_03_105401_create_categories_table.php`
-   `2025_11_03_105402_create_transactions_table.php`
-   `2025_11_03_105403_create_budgets_table.php`
-   `2025_11_03_115327_create_personal_access_tokens_table.php`

## Schema Discrepancies & Recommendations

### 1. Users Table - Missing Columns

**Issue**: Migration doesn't include `email_verified_at` and `remember_token` columns that Laravel Auth typically uses.

**Impact**:

-   Email verification features won't work
-   "Remember me" functionality won't work

**Recommendation**: Add migration to include these columns:

```php
$table->timestamp('email_verified_at')->nullable();
$table->rememberToken();
```

### 2. Transactions Table - category_id Nullability

**Issue**: Migration has `category_id` as NOT NULL, but business logic might need uncategorized transactions.

**Current State**:

-   Migration: `$table->foreignId('category_id');` (NOT NULL)
-   Model fillable: includes `category_id`

**Recommendation**: If uncategorized transactions are needed, update migration:

```php
$table->foreignId('category_id')->nullable();
```

### 3. Transaction Description Column Type

**Issue**: Migration uses `string('description')` (VARCHAR 255) but documentation suggested `text`.

**Current State**: VARCHAR(255) - limited to 255 characters

**Recommendation**:

-   Keep as-is if 255 chars is sufficient
-   Or update to `text()` for longer descriptions

### 4. Mass Assignment Security

**Issue**: All models include `user_id` in `$fillable`, which could be a security risk.

**Current State**:

-   Category: `protected $fillable = ['user_id', 'name', 'icon'];`
-   Transaction: `protected $fillable = ['user_id', 'category_id', 'amount', 'description', 'date'];`
-   Budget: `protected $fillable = ['user_id', 'category_id', 'limit', 'period', 'start_date', 'end_date'];`

**Recommendation**: Remove `user_id` from `$fillable` and always create through relationships:

```php
$user->categories()->create($data); // user_id set automatically
```

### 5. Foreign Key Constraints

**Issue**: Migrations use `foreignId()` but don't specify ON DELETE/ON UPDATE behavior.

**Current State**: Default database behavior applies

**Recommendation**: Add explicit constraints:

```php
$table->foreignId('user_id')->constrained()->onDelete('cascade');
$table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
```

## Seeders

-   `DatabaseSeeder.php`: Main seeder orchestrator
-   User factories, category factories, transaction factories, budget factories
-   `DemoDataSeeder.php`: Creates demo user with sample data (if implemented)

## Notes

-   All timestamps use Laravel's automatic management
-   Soft deletes enable "trash" functionality for users, categories, and transactions
-   UUID not used (standard auto-increment IDs)
-   No explicit audit trail tables (rely on timestamps)
-   Budget model intentionally does NOT use soft deletes
-   User model includes SoftDeletes but migration may need updating for complete functionality

## Verification Checklist

-   [x] Users table has soft deletes
-   [x] Categories table has soft deletes
-   [x] Transactions table has soft deletes
-   [x] Budgets table does NOT have soft deletes
-   [x] All models have correct relationships defined
-   [x] Fillable attributes documented
-   [x] Cast attributes documented
-   [ ] Foreign key constraints need explicit ON DELETE behavior
-   [ ] Users table missing email_verified_at and remember_token
-   [ ] Mass assignment security (user_id in fillable)
-   [ ] Transaction category_id nullability consideration

**Last Verified**: November 6, 2025
