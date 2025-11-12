# Date and Time Fields in FinTrack: Complete Guide

## Overview

This document explains every date and time-related field in the FinTrack application, why they exist, what they achieve, and how they work together to provide accurate financial tracking across timezones.

---

## Table of Contents

1. [Field Categories](#field-categories)
2. [User Model Date Fields](#user-model-date-fields)
3. [Transaction Model Date Fields](#transaction-model-date-fields)
4. [Budget Model Date Fields](#budget-model-date-fields)
5. [Category Model Date Fields](#category-model-date-fields)
6. [Token Expiration Fields](#token-expiration-fields)
7. [Date Handling Strategy](#date-handling-strategy)
8. [API Response Formats](#api-response-formats)
9. [Why These Choices Matter](#why-these-choices-matter)

---

## Field Categories

Date and time fields in FinTrack fall into three distinct categories:

### 1. **Business Date Fields** (DATE type)
- **Purpose**: Track when financial events occurred from the user's perspective
- **Type**: `DATE` (no time component)
- **Format**: `Y-m-d` (e.g., "2025-01-15")
- **Examples**: `transactions.date`, `budgets.start_date`, `budgets.end_date`
- **Why**: Financial events are day-specific, not second-specific. You spent money "on January 15th", not "at 2025-01-15T14:32:01Z"

### 2. **Audit Trail Fields** (TIMESTAMP type)
- **Purpose**: Track when records were created/modified in the system
- **Type**: `TIMESTAMP` (includes date + time + timezone)
- **Format**: ISO 8601 (e.g., "2025-01-15T14:32:01+00:00")
- **Examples**: `created_at`, `updated_at`, `deleted_at`
- **Why**: System operations need precise timing for debugging, auditing, and data integrity

### 3. **Configuration Fields** (STRING type)
- **Purpose**: Store user preferences that affect date handling
- **Type**: `VARCHAR/STRING`
- **Examples**: `users.timezone`
- **Why**: Users need to see dates in their local context, not server time

---

## User Model Date Fields

### `timezone` (VARCHAR 64, nullable)

**Type**: String configuration field  
**Database Column**: `users.timezone`  
**Example Values**: `"America/New_York"`, `"Europe/London"`, `"Asia/Tokyo"`, `null`

#### Why It Exists
- Users travel and live in different timezones
- Financial dates should match what the user sees on their calendar
- When a user creates a transaction "today", "today" means their local day, not the server's day

#### What It Achieves
1. **Accurate Date Defaults**: When creating a transaction without specifying a date, the system uses "today" in the user's timezone
2. **Consistent User Experience**: Dates always make sense to the user
3. **Travel Support**: Users can update their timezone when traveling

#### How It Works
Three-level fallback system in `TransactionStoreRequest::prepareForValidation()`:

```php
$tz = $this->user()->timezone        // 1. User's saved preference
   ?: $this->header('X-Timezone')     // 2. Request header override
   ?: config('app.timezone', 'UTC');  // 3. System default (UTC)

$this->merge([
    'date' => $this->date ?? now($tz)->toDateString(), // Uses user's "today"
]);
```

**Flow Example**:
- User in Tokyo (UTC+9) creates transaction at 11 PM local time
- Without timezone: Would default to server date (possibly tomorrow in UTC)
- With timezone: Defaults to "today" in Tokyo, which is correct

### `created_at` (TIMESTAMP)

**Type**: Audit trail field  
**Database Column**: `users.created_at`  
**Format**: ISO 8601 timestamp  
**Automatic**: Set by Laravel when user registers

#### Why It Exists
- Track account age
- Audit when users joined
- Debug registration issues
- Analyze user growth over time

#### What It Achieves
- **Account History**: Shows how long user has been a customer
- **Debugging**: "User registered 5 minutes ago but has issues" vs "User registered 2 years ago"
- **Analytics**: User growth trends, cohort analysis

#### Current Status
✅ **Included in API responses**: All auth endpoints return user data with `created_at` in ISO 8601 format

### `updated_at` (TIMESTAMP)

**Type**: Audit trail field  
**Database Column**: `users.updated_at`  
**Format**: ISO 8601 timestamp  
**Automatic**: Updated by Laravel on every save

#### Why It Exists
- Track profile changes
- Detect stale accounts
- Audit recent activity

#### What It Achieves
- **Activity Tracking**: Know when user last updated profile
- **Security**: "Password changed yesterday" helps detect compromises
- **Data Freshness**: Identify outdated profiles

#### Current Status
✅ **Included in API responses**: All user data includes `updated_at`

### `deleted_at` (TIMESTAMP, nullable)

**Type**: Soft delete audit field  
**Database Column**: `users.deleted_at`  
**Format**: ISO 8601 timestamp  
**Automatic**: Set by Laravel when `delete()` is called

#### Why It Exists
- **Data Retention**: Don't actually delete user data (legal/historical reasons)
- **Referential Integrity**: Transactions still reference deleted users
- **Recovery**: Can restore deleted accounts

#### What It Achieves
1. **Historical Accuracy**: Past transactions still show who created them
2. **Compliance**: Meet data retention requirements
3. **Undo Capability**: User can recover account within grace period
4. **Foreign Key Safety**: No orphaned transactions

#### How It Works
```php
// In User model
use SoftDeletes;

// In AuthController::destroy()
$user->delete(); // Sets deleted_at to NOW instead of actually deleting

// Queries automatically exclude soft-deleted users
User::all(); // Only active users
User::withTrashed()->get(); // Include deleted users
```

---

## Transaction Model Date Fields

### `date` (DATE)

**Type**: Business date field  
**Database Column**: `transactions.date`  
**Format**: `Y-m-d` (no time component)  
**Example**: `"2025-01-15"`

#### Why It Exists
- Transactions are day-specific events: "I bought coffee on January 15th"
- Budget tracking compares dates: "How much did I spend this month?"
- Date precision is 1 day, not 1 second

#### What It Achieves
1. **User-Centric Dates**: Dates match user's calendar, not server time
2. **Simple Queries**: `WHERE date BETWEEN '2025-01-01' AND '2025-01-31'` works perfectly
3. **No Timezone Confusion**: "2025-01-15" is unambiguous, "2025-01-15T23:00:00Z" could be two different days depending on user timezone

#### How It Works

**Creation (TransactionStoreRequest)**:
```php
protected function prepareForValidation()
{
    $tz = $this->user()->timezone ?: $this->header('X-Timezone') ?: 'UTC';
    
    $this->merge([
        'date' => $this->date ?? now($tz)->toDateString(),
    ]);
}
```

**Budget Calculations (BudgetService)**:
```php
$spent = Transaction::query()
    ->where('user_id', $budget->user_id)
    ->where('category_id', $budget->category_id)
    ->whereDate('date', '>=', $budget->start_date->toDateString())
    ->whereDate('date', '<=', $budget->end_date->toDateString())
    ->sum('amount');
```

**API Response (TransactionResource)**:
```php
'date' => $this->date?->format('Y-m-d'), // "2025-01-15"
```

#### Why Not `datetime` or `timestamp`?
❌ **Bad**: Storing `2025-01-15T23:00:00Z` for a transaction
- What day is that? January 15th or 16th depends on user timezone
- Requires complex timezone conversions in every query
- Over-precision: We don't care what *second* you bought coffee

✅ **Good**: Storing `2025-01-15` as DATE
- Always January 15th, regardless of timezone
- Simple queries with `whereDate()`
- Matches how users think about money

### `created_at` (TIMESTAMP)

**Type**: Audit trail field  
**Database Column**: `transactions.created_at`  
**Format**: ISO 8601 timestamp

#### Why It Exists
- Track *when the record was created in the system*
- Different from `date` (when the transaction occurred financially)
- User might enter last week's transactions today

#### What It Achieves
1. **Audit Trail**: "Transaction was created today but dated last week"
2. **Bulk Import Detection**: Many transactions with same `created_at` = bulk import
3. **Edit History**: Combined with `updated_at`, shows if transaction was edited
4. **Debugging**: "User complains about missing transaction created 5 minutes ago"

#### Example Scenario
```
User on January 20th enters a transaction from January 15th:
- transaction.date = "2025-01-15"       (when they spent the money)
- transaction.created_at = "2025-01-20T10:30:00Z" (when they entered it)
```

This is **correct** and **necessary** - they're tracking different things!

#### Current Status
✅ **Now included in API responses** (as of this standardization)

### `updated_at` (TIMESTAMP)

**Type**: Audit trail field  
**Database Column**: `transactions.updated_at`  
**Format**: ISO 8601 timestamp

#### Why It Exists
- Track when transaction details were modified
- Detect data changes for sync/conflict resolution
- Audit trail for corrections

#### What It Achieves
1. **Change Detection**: Know if amount or category was corrected
2. **Sync Support**: "Updated since last sync at timestamp X"
3. **Fraud Detection**: Many transactions updated at once = suspicious
4. **Edit History**: Different from `created_at` shows edits occurred

#### Current Status
✅ **Now included in API responses**

### `deleted_at` (TIMESTAMP, nullable)

**Type**: Soft delete audit field  
**Database Column**: `transactions.deleted_at`

#### Why It Exists
- Historical budget calculations need deleted transactions
- Restore accidentally deleted transactions
- Audit trail of deletions

#### What It Achieves
1. **Budget Integrity**: "You spent $500 this month" includes deleted transactions (they still happened!)
2. **Undo**: User can restore within grace period
3. **Audit**: Track what was deleted and when

#### How It Works
```php
// In TransactionResource
$this->loadMissing(['category' => fn($q) => $q->withTrashed()]);

'category' => $this->category ? [
    'id' => $this->category->id,
    'name' => $this->category->name,
    'icon' => $this->category->icon,
    'deleted' => !is_null($this->category->deleted_at), // Show if category was deleted
] : null,
```

Even if category is deleted, transactions still show the category name!

---

## Budget Model Date Fields

### `start_date` (DATE)

**Type**: Business date field  
**Database Column**: `budgets.start_date`  
**Format**: `Y-m-d`

#### Why It Exists
- Budgets cover specific date ranges: "January 1-31" or "Q1 2025"
- Need inclusive start date for calculations

#### What It Achieves
1. **Date Range Queries**: Find transactions in budget period
2. **Period Validation**: Ensure start is before end
3. **User Control**: Custom budget periods (weekly, monthly, etc.)

#### How It Works
```php
// BudgetService calculates spending
$spent = Transaction::where('user_id', $budget->user_id)
    ->whereDate('date', '>=', $budget->start_date->toDateString())
    ->whereDate('date', '<=', $budget->end_date->toDateString())
    ->sum('amount');
```

### `end_date` (DATE)

**Type**: Business date field  
**Database Column**: `budgets.end_date`  
**Format**: `Y-m-d`

#### Why It Exists
- Budgets need end dates to be meaningful
- Calculate progress: "We're day 15 of 31"

#### What It Achieves
1. **Inclusive Range**: Transactions on `end_date` are included
2. **Progress Tracking**: Compare current date to end date
3. **Validation**: Must be after `start_date`

### `created_at` and `updated_at` (TIMESTAMP)

#### Why They Exist
- Track when budgets were created/modified
- Detect budget changes mid-period
- Audit trail for budget adjustments

#### What They Achieve
1. **Change History**: "Budget was increased mid-month"
2. **Sync Support**: Detect updates since last sync
3. **Debugging**: "Budget created today but showing wrong data"

#### Current Status
✅ **Now included in API responses**

---

## Category Model Date Fields

### `created_at` and `updated_at` (TIMESTAMP)

#### Why They Exist
- Track when categories were created/renamed
- Audit trail for category changes
- Detect recently added categories

#### What They Achieve
1. **Category Lifecycle**: Know when categories were added
2. **Change Tracking**: Detect renamed categories
3. **Sync Support**: Find categories updated since last sync

#### Current Status
✅ **Now included in API responses**

### `deleted_at` (TIMESTAMP, nullable)

#### Why It Exists
- Can't hard-delete categories (transactions reference them)
- Historical integrity: transactions keep showing category name

#### What It Achieves
1. **Data Integrity**: Old transactions still show category
2. **Referential Safety**: No broken foreign keys
3. **Restore Capability**: Undelete categories

#### How It Works
```php
// In TransactionResource
$this->loadMissing(['category' => fn($q) => $q->withTrashed()]);

// Even deleted categories appear in transaction responses
'category' => $this->category ? [
    'name' => $this->category->name,
    'deleted' => !is_null($this->category->deleted_at),
] : null,
```

---

## Token Expiration Fields

### `expires_at` (String - ISO 8601)

**Type**: Configuration/security field  
**Format**: ISO 8601 timestamp string  
**Example**: `"2025-01-15T15:30:00+00:00"`

#### Why It Exists
- API tokens must expire for security
- Clients need to know when to refresh

#### What It Achieves
1. **Security**: Leaked tokens expire automatically
2. **Refresh Logic**: Client knows when to call `/refresh`
3. **Session Management**: Clear token lifetime

#### How It Works
```php
// In AuthController::issueToken()
$ttlMinutes = config('token.ttl_minutes', 60); // Default: 1 hour
$expiresAt = now()->addMinutes($ttlMinutes);
$token = $user->createToken('api-token', ['*'], $expiresAt);

return [
    'plain' => $token->plainTextToken,
    'expires_at' => $expiresAt->toIso8601String(),
    'expires_in' => $ttlMinutes * 60, // seconds
];
```

### `expires_in` (Integer - seconds)

**Type**: Configuration field  
**Format**: Integer seconds  
**Example**: `3600` (1 hour)

#### Why It Exists
- Easier for clients to calculate: `issued_at + expires_in = expires_at`
- Standard OAuth2 format

#### What It Achieves
1. **Client Convenience**: Set timeout = expires_in
2. **Standard Format**: Matches OAuth2 spec
3. **Quick Math**: No date parsing needed

---

## Date Handling Strategy

### The Three-Level Timezone Fallback

Every time a transaction needs a default date, the system uses this hierarchy:

```php
$tz = $this->user()->timezone        // Level 1: User preference
   ?: $this->header('X-Timezone')     // Level 2: Request override
   ?: config('app.timezone', 'UTC');  // Level 3: System default
```

#### Level 1: User's Saved Timezone
- **Source**: `users.timezone` column
- **When**: User sets timezone in profile
- **Example**: User in New York has `timezone = "America/New_York"`
- **Why**: Persistent preference, works across devices

#### Level 2: Request Header (`X-Timezone`)
- **Source**: HTTP header in API request
- **When**: Mobile app detects device timezone
- **Example**: `X-Timezone: Europe/Paris`
- **Why**: Handles traveling users without changing profile

#### Level 3: System Default (UTC)
- **Source**: `config/app.php` - `'timezone' => 'UTC'`
- **When**: User has no preference and no header
- **Example**: First transaction by new user
- **Why**: Safe fallback, consistent server time

### Why This Matters

**Scenario: User in Tokyo Creates Transaction**

Without timezone support:
```
Server time: 2025-01-15 02:00 UTC
User's local time: 2025-01-15 11:00 JST (UTC+9)
Default date: 2025-01-15 (correct by luck)

Later that day...
Server time: 2025-01-15 16:00 UTC
User's local time: 2025-01-16 01:00 JST (next day!)
Default date: 2025-01-15 (WRONG - should be 2025-01-16)
```

With timezone support:
```
User has timezone = "Asia/Tokyo"
Default date: now('Asia/Tokyo')->toDateString()
Always matches user's calendar day ✓
```

---

## API Response Formats

All date fields now follow consistent formatting in API responses:

### Business Dates (DATE fields)
```json
{
  "date": "2025-01-15",
  "start_date": "2025-01-01",
  "end_date": "2025-01-31"
}
```
**Format**: `Y-m-d`  
**Why**: Simple, unambiguous, matches user calendar

### Audit Timestamps (TIMESTAMP fields)
```json
{
  "created_at": "2025-01-15T10:30:45+00:00",
  "updated_at": "2025-01-20T14:22:11+00:00",
  "deleted_at": null
}
```
**Format**: ISO 8601 with timezone  
**Why**: Internationally standardized, includes timezone, parseable by all clients

### Token Expiration
```json
{
  "expires_at": "2025-01-15T11:30:00+00:00",
  "expires_in": 3600
}
```
**Format**: ISO 8601 + integer seconds  
**Why**: Both formats for client convenience

---

## Why These Choices Matter

### ✅ Separation of Concerns

**Business Dates** (DATE) vs **Audit Timestamps** (TIMESTAMP)
- They track *different things* and should be *different types*
- `transaction.date` = "When did I spend money?" (user perspective)
- `transaction.created_at` = "When was this record created?" (system perspective)

**Real Example**:
```
User on vacation enters last week's expenses:
- All transactions have date = last week (correct!)
- All transactions have created_at = today (correct!)
- This is NOT a conflict - they mean different things
```

### ✅ Timezone Safety

Using DATE for business dates eliminates entire classes of bugs:
- No "transaction appears on wrong day" bugs
- No complex timezone conversions in queries
- No ambiguity about which day a transaction belongs to

### ✅ Soft Deletes = Data Integrity

Never hard-deleting data means:
- Budget calculations stay accurate
- Historical reports don't break
- Can restore accidentally deleted data
- Audit trail for compliance

### ✅ Standard Formats

ISO 8601 timestamps:
- Universally parseable
- Include timezone information
- Sortable as strings
- Human-readable

### ✅ Explicit Over Implicit

Three-level timezone fallback:
- Tries user preference first (best)
- Falls back to request header (good)
- Uses system default last (safe)
- Never silent failures or wrong assumptions

---

## Migration History

### Removed Fields

#### `date_local` (previously in transactions - COMMENTED OUT)
- **Was**: VARCHAR storing date + timezone separately
- **Why Removed**: Redundant - we calculate this from `date` + `user.timezone`
- **Better**: Single source of truth in `date` field

#### `occurred_at_utc` (previously in transactions - COMMENTED OUT)
- **Was**: TIMESTAMP storing transaction time in UTC
- **Why Removed**: Over-precision - we don't need sub-day accuracy
- **Better**: Simple DATE field matches how users think about money

**Why This Simplification?**
```php
// Old way (complex)
- Store: occurred_at_utc, date_local, timezone
- Query: Complex conversions everywhere
- Risk: Three fields can get out of sync

// New way (simple)
- Store: date (DATE)
- Query: WHERE date BETWEEN x AND y
- Risk: None - single source of truth
```

---

## Summary: What We Achieve

### For Users
1. **Accurate Dates**: Transactions match their calendar
2. **Timezone Support**: Works correctly when traveling
3. **Simple UX**: No confusing timestamps, just dates

### For Developers
1. **Simple Queries**: No complex timezone math
2. **Type Safety**: DATE vs TIMESTAMP is explicit
3. **Debugging**: Can distinguish "when entered" from "when occurred"

### For The Business
1. **Accurate Budgets**: Date calculations work correctly
2. **Audit Trail**: Complete history of changes
3. **Data Integrity**: Soft deletes preserve relationships
4. **Compliance**: Timestamps for legal requirements

### For Operations
1. **Timezone-Aware**: Handles global users correctly
2. **Standard Formats**: ISO 8601 for interoperability
3. **Debuggable**: Can track when things were created vs when they occurred

---

## Quick Reference

| Field | Type | Purpose | Format | Example |
|-------|------|---------|--------|---------|
| `users.timezone` | VARCHAR | User preference | IANA timezone | `"America/New_York"` |
| `transactions.date` | DATE | When transaction occurred | Y-m-d | `"2025-01-15"` |
| `budgets.start_date` | DATE | Budget period start | Y-m-d | `"2025-01-01"` |
| `budgets.end_date` | DATE | Budget period end | Y-m-d | `"2025-01-31"` |
| `*.created_at` | TIMESTAMP | Record creation time | ISO 8601 | `"2025-01-15T10:30:00Z"` |
| `*.updated_at` | TIMESTAMP | Last modification time | ISO 8601 | `"2025-01-20T14:22:00Z"` |
| `*.deleted_at` | TIMESTAMP | Soft delete time | ISO 8601 | `"2025-02-01T09:15:00Z"` |
| `expires_at` | String | Token expiration | ISO 8601 | `"2025-01-15T11:30:00Z"` |
| `expires_in` | Integer | Seconds until expiration | Seconds | `3600` |

---

## Conclusion

Every date field in FinTrack serves a specific purpose:
- **Business dates** (`DATE`) track when things happened financially
- **Audit timestamps** (`TIMESTAMP`) track system operations
- **Timezone configs** (`VARCHAR`) ensure user-centric dates

This separation provides:
- ✅ Simple, bug-free queries
- ✅ Accurate timezone handling
- ✅ Complete audit trails
- ✅ Data integrity through soft deletes
- ✅ International standards compliance

The result is a robust, user-friendly financial tracking system that works correctly across timezones and preserves data integrity.
