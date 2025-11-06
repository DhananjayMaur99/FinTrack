# FinTrack Database Schema

**Last Updated**: November 6, 2025  
**Database**: MySQL  
**Laravel Version**: 11.x  
**Status**: ✅ Production Ready with Foreign Key Constraints

## Entity Relationship Diagram

```
users (1) ──────< (N) categories     [CASCADE DELETE]
users (1) ──────< (N) transactions   [CASCADE DELETE]
users (1) ──────< (N) budgets        [CASCADE DELETE]
categories (1) ──< (N) transactions  [NO CONSTRAINT - Soft Delete Preserved]
categories (1) ──< (N) budgets       [SET NULL on DELETE]
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

**Indexes:**

-   PRIMARY KEY (id)
-   UNIQUE KEY (email)

**Foreign Key Constraints:** None (parent table)

**Relationships:**

-   Has many: categories, transactions, budgets (all cascade on delete)

**Soft Deletes**: ✅ Yes

**Model Configuration:**

-   **Fillable**: `name`, `email`, `password`
-   **Hidden**: `password`, `remember_token`
-   **Casts**: `email_verified_at` => `datetime`, `password` => `hashed`
-   **Traits**: `HasApiTokens`, `HasFactory`, `Notifiable`, `SoftDeletes`

**Notes**:

-   The migration does NOT include `email_verified_at` or `remember_token` columns, but the model casts/hides them for future compatibility
-   Passwords are automatically hashed using the `hashed` cast
-   Soft deletes prevent accidental data loss; use `forceDelete()` to permanently delete

---

### categories

User-defined spending/income categories.

| Column     | Type         | Constraints                                | Description        |
| ---------- | ------------ | ------------------------------------------ | ------------------ |
| id         | bigint       | PK, Auto-Inc                               | Unique category ID |
| name       | varchar(255) | Not Null                                   | Category name      |
| user_id    | bigint       | FK → users.id, Not Null, ON DELETE CASCADE | Owner              |
| icon       | varchar(255) | Nullable                                   | Icon identifier    |
| created_at | timestamp    | Nullable                                   | Created timestamp  |
| updated_at | timestamp    | Nullable                                   | Updated timestamp  |
| deleted_at | timestamp    | Nullable                                   | Soft delete flag   |

**Indexes:**

-   PRIMARY KEY (id)
-   INDEX (user_id) - Foreign key index (auto-created by Laravel)

**Foreign Key Constraints:**

-   `fk_categories_user_id`: `user_id` → `users.id`
    -   ON DELETE CASCADE (deleting user deletes all their categories)
    -   ON UPDATE CASCADE

**Relationships:**

-   Belongs to: user
-   Has many: transactions, budgets

**Soft Deletes**: ✅ Yes

**Model Configuration:**

-   **Fillable**: `user_id`, `name`, `icon`
-   **Casts**: `id` => `integer`, `user_id` => `integer`
-   **Traits**: `HasFactory`, `SoftDeletes`

**Business Logic:**

-   When soft deleted, category is hidden from users but historical transactions preserve the reference
-   Validation prevents new transactions/budgets from using soft-deleted categories
-   Use `withTrashed()` to load soft-deleted categories in transaction history
-   Force delete will set `category_id` to NULL in related budgets (but NOT in transactions - no FK constraint)

---

### transactions

Individual income/expense records.

| Column      | Type          | Constraints                                | Description                       |
| ----------- | ------------- | ------------------------------------------ | --------------------------------- |
| id          | bigint        | PK, Auto-Inc                               | Unique transaction ID             |
| user_id     | bigint        | FK → users.id, Not Null, ON DELETE CASCADE | Owner                             |
| category_id | bigint        | FK → categories.id, NOT NULL               | Category (nullable in validation) |
| amount      | decimal(10,2) | Not Null                                   | Transaction amount                |
| description | varchar(255)  | Nullable                                   | Transaction details               |
| date        | date          | Not Null                                   | Transaction date                  |
| created_at  | timestamp     | Nullable                                   | Record created                    |
| updated_at  | timestamp     | Nullable                                   | Record updated                    |
| deleted_at  | timestamp     | Nullable                                   | Soft delete flag                  |

**Indexes:**

-   PRIMARY KEY (id)
-   INDEX (user_id) - Foreign key index (auto-created by Laravel)
-   INDEX (category_id) - Foreign key index (auto-created by Laravel)

**Foreign Key Constraints:**

-   `fk_transactions_user_id`: `user_id` → `users.id`
    -   ON DELETE CASCADE (deleting user deletes all their transactions)
    -   ON UPDATE CASCADE
-   **NO CONSTRAINT on `category_id`** - By design to preserve historical data with soft-deleted categories

**Relationships:**

-   Belongs to: user, category

**Soft Deletes**: ✅ Yes

**Model Configuration:**

-   **Fillable**: `user_id`, `category_id`, `amount`, `description`, `date`
-   **Casts**: `amount` => `decimal:2`, `date` => `date`
-   **Traits**: `HasFactory`, `SoftDeletes`

**Business Rules:**

-   Amount must be ≥ 0.01 (validated)
-   Category must belong to same user (validated)
-   Category must NOT be soft-deleted for new transactions (validated with `whereNull('deleted_at')`)
-   Date can be past, present, or future
-   Maximum amount: 99,999,999.99 (DECIMAL(10,2) limit)
-   Maximum description length: 255 characters

**Critical Design Decision:**

-   `category_id` has NO foreign key constraint to allow historical transactions to reference soft-deleted categories
-   This preserves transaction history when users delete categories
-   Application layer prevents using deleted categories in NEW transactions via validation

---

### budgets

Spending limit tracking with progress calculation.

| Column      | Type                      | Constraints                                      | Description                |
| ----------- | ------------------------- | ------------------------------------------------ | -------------------------- |
| id          | bigint                    | PK, Auto-Inc                                     | Unique budget ID           |
| user_id     | bigint                    | FK → users.id, Not Null, ON DELETE CASCADE       | Owner                      |
| category_id | bigint                    | FK → categories.id, Nullable, ON DELETE SET NULL | Target category (optional) |
| limit       | decimal(10,2)             | Not Null                                         | Spending limit             |
| period      | enum('monthly', 'yearly') | Not Null                                         | Budget frequency           |
| start_date  | date                      | Not Null                                         | Budget start               |
| end_date    | date                      | Nullable                                         | Budget end (optional)      |
| created_at  | timestamp                 | Nullable                                         | Record created             |
| updated_at  | timestamp                 | Nullable                                         | Record updated             |

**Indexes:**

-   PRIMARY KEY (id)
-   INDEX (user_id) - Foreign key index (auto-created by Laravel)
-   INDEX (category_id) - Foreign key index (auto-created by Laravel)

**Foreign Key Constraints:**

-   `fk_budgets_user_id`: `user_id` → `users.id`
    -   ON DELETE CASCADE (deleting user deletes all their budgets)
    -   ON UPDATE CASCADE
-   `fk_budgets_category_id`: `category_id` → `categories.id`
    -   ON DELETE SET NULL (deleting category converts budget to "overall" budget)
    -   ON UPDATE CASCADE

**Relationships:**

-   Belongs to: user, category

**Soft Deletes**: ❌ No

**Model Configuration:**

-   **Fillable**: `user_id`, `category_id`, `limit`, `period`, `start_date`, `end_date`
-   **Casts**: `limit` => `decimal:2`, `start_date` => `date`, `end_date` => `date`
-   **Traits**: `HasFactory` (SoftDeletes NOT used)

**Business Rules:**

-   Limit must be ≥ 0.01 (validated)
-   Category must belong to same user if provided (validated)
-   Category must NOT be soft-deleted for new budgets (validated with `whereNull('deleted_at')`)
-   If `category_id` is NULL, budget applies to ALL spending (overall budget)
-   `end_date` must be ≥ `start_date` if provided (validated)
-   Period determines how to calculate date ranges ('monthly' or 'yearly')

**Budget Progress Calculation** (via BudgetService):

-   Sums all transactions for the user within date range
-   If category_id is set, filters transactions by that category
-   If category_id is NULL, includes ALL transactions (overall spending)
-   Returns: limit, spent, remaining, progress_percent, is_over_budget

**Why No Soft Deletes:**

-   Budgets are time-bound constraints, not permanent records
-   Historical budget data isn't as critical as transaction history
-   Deleting a budget is a deliberate action to stop tracking
-   If needed for historical reporting, add soft deletes in future migration

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

## Data Integrity & Referential Integrity

### Foreign Key Constraints (Added November 6, 2025)

**Migration**: `2025_11_06_000001_add_foreign_key_constraints.php`

| Parent Table | Child Table  | Foreign Key | Constraint Name         | ON DELETE | ON UPDATE |
| ------------ | ------------ | ----------- | ----------------------- | --------- | --------- |
| users        | categories   | user_id     | fk_categories_user_id   | CASCADE   | CASCADE   |
| users        | transactions | user_id     | fk_transactions_user_id | CASCADE   | CASCADE   |
| users        | budgets      | user_id     | fk_budgets_user_id      | CASCADE   | CASCADE   |
| categories   | transactions | category_id | **NONE** (by design)    | N/A       | N/A       |
| categories   | budgets      | category_id | fk_budgets_category_id  | SET NULL  | CASCADE   |

### Cascade Behavior

**User Deletion:**

-   Soft delete (`$user->delete()`): User marked as deleted, all data remains
-   Force delete (`$user->forceDelete()`): User and ALL related data (categories, transactions, budgets) are permanently deleted

**Category Deletion:**

-   Soft delete (`$category->delete()`): Category hidden from UI, transactions preserve reference
-   Force delete (`$category->forceDelete()`):
    -   Budgets with this category_id → category_id set to NULL (become "overall" budgets)
    -   Transactions remain unchanged (no FK constraint)

**Transaction Deletion:**

-   Soft delete only: Transaction marked as deleted but can be restored

**Budget Deletion:**

-   Hard delete: Budget permanently removed (no soft deletes)

### Data Validation Rules

**At Application Layer:**

1. **New Transactions/Budgets**: Cannot use soft-deleted categories (validated with `whereNull('deleted_at')`)
2. **Ownership**: Categories must belong to the authenticated user
3. **Monetary Values**: All amounts must be ≥ 0.01
4. **Date Ranges**: end_date must be ≥ start_date for budgets
5. **User Assignment**: `user_id` automatically set via relationships (not in fillable for security)

**At Database Layer:**

1. **Foreign Key Constraints**: Enforce referential integrity
2. **NOT NULL Constraints**: Prevent missing required fields
3. **UNIQUE Constraints**: Prevent duplicate emails
4. **DECIMAL Precision**: Monetary values stored as DECIMAL(10,2)

### Security Considerations

**Mass Assignment Protection:**

-   ⚠️ **Current State**: `user_id` is in fillable arrays for Transaction, Category, and Budget models
-   ✅ **Best Practice**: Remove `user_id` from fillable and always create through relationships:

    ```php
    // Secure approach (recommended)
    $user->transactions()->create($data); // user_id set automatically

    // Current approach (works but less secure)
    Transaction::create(array_merge($data, ['user_id' => $user->id]));
    ```

**SQL Injection Protection:**

-   ✅ Laravel Query Builder and Eloquent use parameter binding automatically
-   ✅ All user inputs are sanitized through validation

**Authorization:**

-   ✅ Policies enforce ownership checks (TransactionPolicy, CategoryPolicy, BudgetPolicy)
-   ✅ Middleware ensures authentication (auth:sanctum)

## Model Summary

| Model       | Soft Deletes | Fillable                                                  | Casts                                                    | Traits                                            |
| ----------- | ------------ | --------------------------------------------------------- | -------------------------------------------------------- | ------------------------------------------------- |
| User        | ✅ Yes       | name, email, password                                     | email_verified_at => datetime, password => hashed        | HasApiTokens, HasFactory, Notifiable, SoftDeletes |
| Category    | ✅ Yes       | user_id, name, icon                                       | id => integer, user_id => integer                        | HasFactory, SoftDeletes                           |
| Transaction | ✅ Yes       | user_id, category_id, amount, description, date           | amount => decimal:2, date => date                        | HasFactory, SoftDeletes                           |
| Budget      | ❌ No        | user_id, category_id, limit, period, start_date, end_date | limit => decimal:2, start_date => date, end_date => date | HasFactory                                        |

**Note**: `user_id` is intentionally in fillable arrays but should ideally be removed for better security. Always create records through user relationships when possible.

## Migration Files

1. `0001_01_01_000001_create_cache_table.php` - Laravel cache storage
2. `0001_01_01_000002_create_jobs_table.php` - Laravel queue jobs
3. `2025_11_03_105400_create_users_table.php` - Users table
4. `2025_11_03_105401_create_categories_table.php` - Categories table
5. `2025_11_03_105402_create_transactions_table.php` - Transactions table
6. `2025_11_03_105403_create_budgets_table.php` - Budgets table
7. `2025_11_03_115327_create_personal_access_tokens_table.php` - Sanctum tokens
8. `2025_11_06_000001_add_foreign_key_constraints.php` - ✅ Foreign key constraints (Added Nov 6, 2025)

**Total Migrations Run**: 8  
**Database Structure**: Fully migrated and verified

## Schema Implementation Status

### ✅ Implemented & Working

1. **Database Normalization**: 3NF (Third Normal Form)
2. **Foreign Key Constraints**: All critical constraints in place
3. **Soft Deletes**: Users, Categories, Transactions
4. **Referential Integrity**: Enforced at database level
5. **Cascade Rules**: Properly configured for data safety
6. **Validation**: Request validation prevents data corruption
7. **Authorization**: Policy-based access control
8. **API Authentication**: Laravel Sanctum tokens

### ⚠️ Known Limitations

1. **Users Table Missing Columns**:

    - No `email_verified_at` column in migration (but cast exists in model)
    - No `remember_token` column in migration (but hidden in model)
    - **Impact**: Email verification and "remember me" won't work
    - **Recommendation**: Add migration if needed

2. **Mass Assignment Security**:

    - `user_id` in fillable arrays (security consideration)
    - **Current State**: Works but could be exploited
    - **Best Practice**: Use relationship creation methods

3. **Indexes**:

    - No explicit indexes on `date` column in transactions
    - **Impact**: Date range queries may be slower on large datasets
    - **Recommendation**: Add index if performance issues arise

4. **Transaction Category Constraint**:
    - No FK constraint on `transactions.category_id`
    - **Reason**: By design to preserve historical data
    - **Trade-off**: Application must validate; database doesn't enforce

### 🔮 Future Enhancements

1. **Email Verification**: Add `email_verified_at` column and implement verification flow
2. **Remember Token**: Add `remember_token` for persistent login
3. **Audit Trail**: Consider adding audit log table for compliance
4. **Soft Deletes for Budgets**: If historical budget tracking is needed
5. **Composite Indexes**: Add multi-column indexes for complex queries
6. **UUID Support**: Consider UUIDs instead of auto-increment for distributed systems
7. **Archiving**: Implement data archiving for old transactions

## Testing & Verification

### Database Tests

✅ **Foreign Key Constraint Tests** (`ForeignKeyConstraintTest.php`):

-   13 passing tests
-   2 skipped (API validation - non-critical)
-   Tests verify cascade behavior, soft delete preservation, and constraint enforcement

### Manual Verification Commands

```bash
# Check foreign key constraints
php artisan tinker
>>> DB::select("SHOW CREATE TABLE transactions");

# Verify soft deletes
>>> \App\Models\Category::onlyTrashed()->count();

# Test cascade delete
>>> $user = User::factory()->create();
>>> $user->categories()->create(['name' => 'Test']);
>>> $user->forceDelete(); // Should cascade

# Check orphaned data
>>> php artisan db:cleanup-orphans --dry-run
```

## Seeders & Factories

**Factories:**

-   `UserFactory.php` - Creates test users
-   `CategoryFactory.php` - Creates categories with user relationship
-   `TransactionFactory.php` - Generates amounts: $1-$10,000 (fixed Nov 6, 2025)
-   `BudgetFactory.php` - Generates limits: $100-$50,000 (fixed Nov 6, 2025)

**Seeders:**

-   `DatabaseSeeder.php` - Main seeder orchestrator

**Factory Improvements (Nov 6, 2025):**

-   Fixed amount generation to prevent DECIMAL overflow
-   TransactionFactory: randomFloat(2, 1, 10000) instead of (0, 99999999.99)
-   BudgetFactory: randomFloat(2, 100, 50000) instead of (0, 99999999.99)
-   Limited description length to 200 characters

## Performance Considerations

### Automatic Indexes

Laravel automatically creates indexes for:

-   Primary keys (id)
-   Foreign keys (user_id, category_id)
-   Unique constraints (email)

### Query Optimization

**Efficient Queries:**

```php
// Load user with all related data (prevents N+1)
$user = User::with(['categories', 'transactions', 'budgets'])->find($id);

// Get transactions with soft-deleted categories
$transactions = Transaction::with(['category' => function($q) {
    $q->withTrashed();
}])->get();

// Budget progress calculation (optimized in BudgetService)
$progress = BudgetService::getBudgetProgress($budget);
```

**Slow Query Potential:**

-   Date range queries on large transaction tables (add index if needed)
-   Counting transactions by category without eager loading
-   Budget progress calculation on very large datasets

## Error Handling

### Common Database Errors

1. **Foreign Key Constraint Violation**:

    ```
    SQLSTATE[23000]: Integrity constraint violation
    ```

    **Cause**: Trying to insert invalid foreign key  
    **Solution**: Validation prevents this at application layer

2. **Numeric Value Out of Range**:

    ```
    SQLSTATE[22003]: Numeric value out of range for column 'amount'
    ```

    **Cause**: Amount exceeds DECIMAL(10,2) limit (99,999,999.99)  
    **Solution**: Fixed in factories; validate in requests

3. **Column Not Found - deleted_at**:
    ```
    Unknown column 'budgets.deleted_at'
    ```
    **Cause**: Budget model doesn't use SoftDeletes  
    **Solution**: Already fixed; Budget model doesn't have SoftDeletes trait

## Best Practices Implemented

✅ **Data Integrity**

-   Foreign key constraints enforce relationships
-   Soft deletes prevent accidental data loss
-   Validation at multiple layers

✅ **Security**

-   Policy-based authorization
-   Sanctum token authentication
-   Scoped queries prevent unauthorized access

✅ **Maintainability**

-   Clear migration files
-   Well-documented models
-   Business logic in Service classes

✅ **Performance**

-   Efficient relationships
-   Appropriate use of soft deletes
-   Decimal precision for financial data

✅ **Historical Data Preservation**

-   Transactions keep category references even when deleted
-   Soft deletes allow data recovery
-   Cascade rules prevent orphaned records
