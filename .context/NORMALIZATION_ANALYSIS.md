# FinTrack Database Normalization & Integrity Analysis

**Date**: November 6, 2025  
**Analyst**: Database Schema Review  
**Status**: ⚠️ **Partially Normalized with Critical Issues**

---

## Executive Summary

Your database schema is **mostly well-normalized** (3NF) but has **CRITICAL referential integrity issues** that will cause data anomalies during CRUD operations.

### Overall Assessment

| Aspect                  | Status             | Severity      |
| ----------------------- | ------------------ | ------------- |
| Normalization Level     | ✅ 3NF Achieved    | Good          |
| Primary Keys            | ✅ Proper          | Good          |
| Foreign Keys Defined    | ✅ Present         | Good          |
| Foreign Key Constraints | 🔴 **MISSING**     | **CRITICAL**  |
| Cascade Rules           | 🔴 **NOT DEFINED** | **CRITICAL**  |
| Data Redundancy         | ✅ Minimal         | Good          |
| Transitive Dependencies | ✅ None            | Good          |
| Potential Anomalies     | ⚠️ **YES**         | **HIGH RISK** |

---

## Normalization Analysis

### ✅ First Normal Form (1NF) - **PASSED**

-   [x] All tables have primary keys
-   [x] All columns contain atomic values (no arrays or lists)
-   [x] No repeating groups
-   [x] Each column contains values of a single type

**Verdict**: Your schema is in 1NF.

---

### ✅ Second Normal Form (2NF) - **PASSED**

-   [x] Schema is in 1NF
-   [x] No partial dependencies (all non-key attributes depend on the entire primary key)
-   [x] All tables use single-column surrogate keys (id)

**Example**:

```
transactions table:
- PK: id
- All attributes (amount, description, date) depend on the full primary key (id)
- user_id and category_id are foreign keys, not part of a composite key
```

**Verdict**: Your schema is in 2NF.

---

### ✅ Third Normal Form (3NF) - **PASSED**

-   [x] Schema is in 2NF
-   [x] No transitive dependencies
-   [x] All non-key attributes depend directly on the primary key

**Example**:

```
transactions table does NOT have:
- category_name (would be derived from categories.name via category_id)
- user_email (would be derived from users.email via user_id)

✅ Instead, it uses foreign keys to reference these entities
```

**Verdict**: Your schema is in 3NF and well-normalized!

---

## 🔴 CRITICAL ISSUES: Referential Integrity Problems

### Problem 1: Missing Foreign Key Constraints

**Current State**: Your migrations define `foreignId()` but DO NOT establish proper constraints.

**Example from categories migration**:

```php
// 🔴 CURRENT - No constraints defined
$table->foreignId('user_id');
```

**What this means**:

-   The column is created as BIGINT UNSIGNED
-   **BUT**: No database-level relationship is enforced
-   Laravel knows about the relationship, but MySQL/PostgreSQL **does not**

---

## Data Anomaly Scenarios (WILL HAPPEN!)

### ⚠️ Anomaly 1: Orphaned Records After User Deletion

**Scenario**:

```sql
-- User has categories, transactions, and budgets
DELETE FROM users WHERE id = 1;

-- Result:
-- 🔴 categories with user_id = 1 still exist (ORPHANED)
-- 🔴 transactions with user_id = 1 still exist (ORPHANED)
-- 🔴 budgets with user_id = 1 still exist (ORPHANED)
```

**Impact**:

-   Data integrity violation
-   Queries will fail when trying to load relationships
-   Application errors when accessing user data
-   Database contains "ghost" data

**Current Workaround**: You use soft deletes on users, so they're never truly deleted. But this is a band-aid, not a solution.

---

### ⚠️ Anomaly 2: Category Deletion and Historical Data

**Business Logic Consideration**:
When a user deletes a category, they don't want it for **future use**, but **historical transactions** with that category are still valid financial records.

**Scenario Without Proper Constraints**:

```sql
-- Category has transactions referencing it
DELETE FROM categories WHERE id = 5;

-- Result with current schema:
-- 🔴 transactions with category_id = 5 still exist (ORPHANED)
-- 🔴 Application will crash when loading transaction.category relationship
-- 🔴 No way to know what category those transactions originally had
```

**Better Approach - Two Options**:

**Option A: Soft Deletes Only (Recommended)**

-   Keep using soft deletes on categories
-   Soft-deleted categories remain in database but hidden from users
-   Historical transactions still link to the category data
-   Category name appears in reports as "Food (deleted)" or similar

**Option B: SET NULL + Store Category Name**

-   Make `transactions.category_id` nullable
-   Add `transactions.category_name` (denormalized) to preserve history
-   When category is deleted, set category_id to NULL but keep the name
-   Transactions become "uncategorized" but retain historical label

**Current Workaround**: Category uses soft deletes, which actually handles this well!

---

### ⚠️ Anomaly 3: Invalid Foreign Key Values

**Scenario**:

```sql
-- Malicious or buggy code attempts to insert invalid reference
INSERT INTO transactions (user_id, category_id, amount, date)
VALUES (999, 888, 50.00, '2025-11-06');
-- Where user_id=999 and category_id=888 don't exist

-- Result:
-- ✅ INSERT SUCCEEDS! (Because no constraint checks it)
-- 🔴 Data integrity violated
-- 🔴 Application will crash when loading this transaction
```

**Impact**:

-   Invalid data in database
-   Application errors
-   Data corruption

---

### ⚠️ Anomaly 4: Update Anomaly (Less Critical)

**Scenario**:

```sql
-- User ID changes (rare, but possible in some systems)
UPDATE users SET id = 100 WHERE id = 1;

-- Result:
-- 🔴 All user_id references (categories, transactions, budgets) still point to id=1
-- 🔴 User's data is now orphaned
```

**Impact**:

-   Complete data loss for the user
-   Would require manual update of all related tables

**Note**: This is theoretical since you use auto-increment IDs that don't change, but shows the lack of referential integrity.

---

## Missing Constraints Summary

| Table        | Foreign Key                 | Missing Constraint          | Consequence                 |
| ------------ | --------------------------- | --------------------------- | --------------------------- |
| categories   | user_id → users.id          | ON DELETE CASCADE/RESTRICT  | Orphaned categories         |
| transactions | user_id → users.id          | ON DELETE CASCADE/RESTRICT  | Orphaned transactions       |
| transactions | category_id → categories.id | ON DELETE SET NULL/RESTRICT | Invalid category references |
| budgets      | user_id → users.id          | ON DELETE CASCADE/RESTRICT  | Orphaned budgets            |
| budgets      | category_id → categories.id | ON DELETE SET NULL/RESTRICT | Invalid category references |

---

## Solutions & Recommendations

### ✅ Solution 1: Add Foreign Key Constraints (RECOMMENDED)

Create a new migration to add proper constraints:

```php
// filepath: database/migrations/2025_11_07_000001_add_foreign_key_constraints.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Categories: If user is deleted, cascade delete all their categories
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });

        // Transactions: If user is deleted, cascade delete their transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // If category is deleted, you have options:
            // Option A: Prevent deletion if transactions exist
            $table->foreign('category_id')
                  ->references('id')->on('categories')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');

            // Option B: Set category_id to NULL (requires nullable column)
            // $table->foreign('category_id')
            //       ->references('id')->on('categories')
            //       ->onDelete('set null')
            //       ->onUpdate('cascade');
        });

        // Budgets: If user is deleted, cascade delete their budgets
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Category is nullable, so SET NULL makes sense
            $table->foreign('category_id')
                  ->references('id')->on('categories')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['category_id']);
        });
    }
};
```

---

### Cascade Strategy Recommendations

| Relationship              | Recommended Action               | Reason                                                                            |
| ------------------------- | -------------------------------- | --------------------------------------------------------------------------------- |
| users → categories        | **CASCADE**                      | Categories are meaningless without a user                                         |
| users → transactions      | **CASCADE**                      | Transactions belong to user, should be deleted with user                          |
| users → budgets           | **CASCADE**                      | Budgets belong to user, should be deleted with user                               |
| categories → transactions | **NO CONSTRAINT** (Soft Deletes) | Historical transactions remain valid. Use soft deletes to hide deleted categories |
| categories → budgets      | **SET NULL**                     | Allow budgets to continue as "overall" budgets                                    |

**Important Note on Categories**:
Since categories use soft deletes, you should **NOT add a foreign key constraint** with `onDelete('restrict')` or `onDelete('set null')`. Instead:

1. **Keep soft deletes** - When user "deletes" a category, it's soft deleted
2. **Historical data preserved** - Transactions keep their category_id reference
3. **Show deleted status** - Display "(deleted)" next to category names in transaction history
4. **Query with trashed** - Use `withTrashed()` when loading transaction categories for history

---

### ✅ Solution 2: Update Initial Migrations (BETTER)

If you're early in development and can recreate the database:

```php
// filepath: database/migrations/2025_11_03_105401_create_categories_table.php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('user_id')
          ->constrained()              // References users.id
          ->onDelete('cascade')        // Delete categories when user deleted
          ->onUpdate('cascade');
    $table->string('icon')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

```php
// filepath: database/migrations/2025_11_03_105402_create_transactions_table.php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')
          ->constrained()
          ->onDelete('cascade')
          ->onUpdate('cascade');
    $table->foreignId('category_id')
          ->constrained()
          ->onDelete('restrict')       // Prevent category deletion if has transactions
          ->onUpdate('cascade');
    $table->decimal('amount', 10, 2);
    $table->string('description')->nullable();
    $table->date('date');
    $table->timestamps();
    $table->softDeletes();
});
```

```php
// filepath: database/migrations/2025_11_03_105403_create_budgets_table.php
Schema::create('budgets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')
          ->constrained()
          ->onDelete('cascade')
          ->onUpdate('cascade');
    $table->foreignId('category_id')
          ->nullable()
          ->constrained()
          ->onDelete('set null')       // Set to null if category deleted
          ->onUpdate('cascade');
    $table->decimal('limit', 10, 2);
    $table->enum('period', ['monthly', 'yearly']);
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->timestamps();
});
```

---

### ✅ Solution 3: Alternative - Make transaction.category_id Nullable

If you want to allow uncategorized transactions:

```php
// New migration
Schema::table('transactions', function (Blueprint $table) {
    $table->foreignId('category_id')
          ->nullable()
          ->change();

    $table->foreign('category_id')
          ->references('id')->on('categories')
          ->onDelete('set null')
          ->onUpdate('cascade');
});
```

---

## Testing Referential Integrity

After adding constraints, test these scenarios:

```php
// Test 1: Try to delete user with data (should cascade)
$user = User::factory()->create();
$category = Category::factory()->create(['user_id' => $user->id]);
$transaction = Transaction::factory()->create([
    'user_id' => $user->id,
    'category_id' => $category->id
]);

// This should cascade delete category and transaction
$user->forceDelete(); // Use forceDelete to bypass soft deletes

// Verify they're gone
$this->assertDatabaseMissing('categories', ['id' => $category->id]);
$this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
```

```php
// Test 2: Soft delete category - transactions remain valid
$category = Category::factory()->create(['name' => 'Food']);
$transaction = Transaction::factory()->create(['category_id' => $category->id]);

// Soft delete the category
$category->delete();

// Transaction still exists and references the category
$this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'category_id' => $category->id]);

// Category is soft deleted
$this->assertSoftDeleted('categories', ['id' => $category->id]);

// Load transaction with deleted category
$loadedTransaction = Transaction::find($transaction->id);
$categoryWithTrashed = $loadedTransaction->category()->withTrashed()->first();
$this->assertEquals('Food', $categoryWithTrashed->name);
```

```php
// Test 3: Prevent creation of transaction with non-existent category
// Note: Without foreign key constraint, this will NOT fail
// You must validate in application layer
$this->expectException(\Illuminate\Validation\ValidationException::class);
$request = new TransactionStoreRequest([
    'category_id' => 9999, // Non-existent category
    'amount' => 50.00,
    'date' => now(),
]);
```

---

## Handling Soft-Deleted Categories in Application

### Update Transaction Resource to Show Deleted Categories

```php
// filepath: app/Http/Resources/TransactionResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Load category even if soft deleted
        $category = $this->category()->withTrashed()->first();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'is_deleted' => $category->trashed(), // Flag for UI
            ] : null,
            'amount' => $this->amount,
            'description' => $this->description,
            'date' => $this->date->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### Update Request Validation to Prevent Deleted Categories

```php
// filepath: app/Http/Requests/TransactionStoreRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                // Only allow active (not deleted) categories
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
                // Also verify category belongs to authenticated user
                function ($attribute, $value, $fail) {
                    $category = \App\Models\Category::find($value);
                    if ($category && $category->user_id !== $this->user()->id) {
                        $fail('The selected category does not belong to you.');
                    }
                },
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
        ];
    }
}
```

### Display in Frontend

```javascript
// Example: Display transaction with deleted category indicator
{
    transaction.category.is_deleted && (
        <span className="text-gray-500">
            {transaction.category.name} <em>(deleted)</em>
        </span>
    );
}
{
    !transaction.category.is_deleted && (
        <span>{transaction.category.name}</span>
    );
}
```

---

## Additional Normalization Considerations

### ✅ Denormalization Opportunities (Optional)

Your schema is well-normalized, but you might consider strategic denormalization for performance:

1. **Add category_name to transactions** (denormalize)

    - Pro: Faster queries, no joins needed
    - Con: Data redundancy, must update on category rename
    - Recommendation: **NOT needed** - Your transaction volume is likely low

2. **Cache budget progress** (computed column)
    - Pro: Faster budget queries
    - Con: Must recalculate on transaction changes
    - Recommendation: **Use Redis/Cache** instead of database column

---

## Normalization Best Practices You're Following

✅ **Good Practices**:

1. Single responsibility per table
2. No repeating groups
3. Proper use of foreign keys (defined)
4. Appropriate use of surrogate keys (auto-increment IDs)
5. No redundant data storage
6. Proper data types for monetary values (DECIMAL)
7. Timestamps for audit trail
8. Soft deletes for user data protection

---

## Final Verdict

### Normalization: ✅ **EXCELLENT** (3NF)

Your schema design is well-normalized and follows best practices.

### Referential Integrity: 🔴 **CRITICAL ISSUES**

Your schema **WILL** have data anomalies without proper foreign key constraints.

### Risk Assessment

| Risk                | Likelihood | Impact       | Priority   |
| ------------------- | ---------- | ------------ | ---------- |
| Orphaned records    | **HIGH**   | **HIGH**     | **URGENT** |
| Invalid references  | **MEDIUM** | **HIGH**     | **URGENT** |
| Data corruption     | **MEDIUM** | **CRITICAL** | **URGENT** |
| Application crashes | **HIGH**   | **HIGH**     | **URGENT** |

---

## Action Plan

### Immediate (Do Now)

1. [ ] Create migration to add foreign key constraints
2. [ ] Decide on cascade strategy for each relationship
3. [ ] Run migration in development
4. [ ] Test all CRUD operations
5. [ ] Test edge cases (delete user with data, delete category with transactions)

### Short Term (This Week)

6. [ ] Add integration tests for referential integrity
7. [ ] Update documentation with constraint information
8. [ ] Review application code for manual cascade logic (can be removed)

### Long Term (Optional)

9. [ ] Consider adding database triggers for complex business rules
10. [ ] Monitor query performance with constraints
11. [ ] Add composite indexes for frequently queried foreign key combinations

---

## Conclusion

Your database is **well-normalized (3NF)** but **lacks critical referential integrity constraints**.

Without adding foreign key constraints with proper ON DELETE/ON UPDATE rules, you **WILL** experience:

-   🔴 Orphaned records
-   🔴 Invalid data references
-   🔴 Application crashes
-   🔴 Data corruption

**Recommendation**: Implement Solution 1 (add constraints migration) **immediately** before deploying to production.

---

**Last Updated**: November 6, 2025  
**Next Review**: After implementing foreign key constraints
