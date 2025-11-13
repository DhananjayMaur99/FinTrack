# FinTrack Developer Guide

## Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Architecture Overview](#architecture-overview)
4. [Application Structure](#application-structure)
5. [Core Concepts](#core-concepts)
6. [Authentication & Authorization](#authentication--authorization)
7. [Data Models](#data-models)
8. [Request Validation](#request-validation)
9. [Business Logic Layer](#business-logic-layer)
10. [API Resources](#api-resources)
11. [Middleware Pipeline](#middleware-pipeline)
12. [Testing Strategy](#testing-strategy)
13. [Design Patterns](#design-patterns)
14. [Common Workflows](#common-workflows)
15. [Adding New Features](#adding-new-features)

---

## Project Overview

**FinTrack** is a personal finance tracking API built with Laravel 11. It allows users to:
- Register and authenticate with JWT-like token authentication (Laravel Sanctum)
- Create categories for organizing transactions
- Record income/expense transactions
- Set budgets with automatic progress tracking
- Track spending against budgets in real-time

**Key Principles:**
- **RESTful API**: Pure JSON API, no web UI
- **Multi-tenancy**: Each user's data is completely isolated
- **Authorization**: Middleware-based ownership validation
- **Timezone-aware**: Supports global users with proper date handling
- **Soft Deletes**: Preserves data integrity and audit trails

---

## Technology Stack

### Backend Framework
- **Laravel 11.x** (PHP 8.4.13)
- **Laravel Sanctum** for API token authentication
- **MySQL** as the primary database

### Testing
- **PHPUnit 11.5.43** for unit and feature tests
- **RefreshDatabase** trait for test isolation
- **Faker** for generating test data
- **JMac's AdditionalAssertions** for enhanced test assertions

### Development Tools
- **Composer** for PHP dependency management
- **Artisan** for CLI commands
- **Xdebug** for debugging (optional)

### Production Requirements
- PHP 8.4+
- MySQL 8.0+
- Composer 2.x

---

## Architecture Overview

FinTrack follows a **layered architecture** pattern:

```
┌─────────────────────────────────────────────┐
│           API Client (Mobile/Web)           │
└─────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────┐
│              Routes (api.php)               │
│  - Public routes (register, login)          │
│  - Protected routes (auth:sanctum)          │
└─────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────┐
│           Middleware Pipeline               │
│  1. ForceJsonResponse (all API routes)      │
│  2. auth:sanctum (protected routes)         │
│  3. owner (resource ownership validation)   │
└─────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────┐
│              Controllers                    │
│  - Thin layer, delegates to services        │
│  - Returns API Resources                    │
└─────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────┐
│         FormRequest Validation              │
│  - Validates incoming data                  │
│  - Auto-returns 422 on failure              │
└─────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────┐
│           Service Layer (Optional)          │
│  - BudgetService for complex logic          │
│  - Encapsulates business rules              │
└─────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────┐
│              Models (Eloquent)              │
│  - User, Category, Transaction, Budget      │
│  - Relationships, Scopes, Casts             │
└─────────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────┐
│              Database (MySQL)               │
│  - Normalized schema                        │
│  - Foreign keys with cascades               │
│  - Soft deletes for data preservation       │
└─────────────────────────────────────────────┘
```

---

## Application Structure

```
FinTrack/
├── app/
│   ├── Console/
│   │   └── Kernel.php                    # Console commands configuration
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   └── AuthController.php    # Authentication endpoints
│   │   │   ├── BudgetController.php      # Budget CRUD
│   │   │   ├── CategoryController.php    # Category CRUD
│   │   │   └── TransactionController.php # Transaction CRUD
│   │   ├── Middleware/
│   │   │   ├── AuthorizeUser.php         # Ownership validation
│   │   │   └── ForceJsonResponse.php     # Forces JSON responses
│   │   ├── Requests/
│   │   │   ├── ApiRequest.php            # Base class for all requests
│   │   │   ├── BudgetStoreRequest.php    # Budget creation validation
│   │   │   ├── BudgetUpdateRequest.php   # Budget update validation
│   │   │   ├── CategoryStoreRequest.php  # Category creation validation
│   │   │   ├── CategoryUpdateRequest.php # Category update validation
│   │   │   ├── LoginUserRequest.php      # Login validation
│   │   │   ├── RegisterUserRequest.php   # Registration validation
│   │   │   ├── TransactionStoreRequest.php   # Transaction creation
│   │   │   ├── TransactionUpdateRequest.php  # Transaction update
│   │   │   └── UserUpdateRequest.php     # Profile update validation
│   │   └── Resources/
│   │       ├── BudgetResource.php        # Budget API response transformer
│   │       ├── CategoryCollection.php    # Category collection response
│   │       ├── CategoryResource.php      # Category API response
│   │       ├── TransactionCollection.php # Transaction collection response
│   │       └── TransactionResource.php   # Transaction API response
│   ├── Models/
│   │   ├── Budget.php                    # Budget model
│   │   ├── Category.php                  # Category model
│   │   ├── Transaction.php               # Transaction model
│   │   └── User.php                      # User model
│   ├── Providers/
│   │   └── AppServiceProvider.php        # Service container bindings
│   └── Services/
│       └── BudgetService.php             # Budget business logic
├── bootstrap/
│   └── app.php                           # Application bootstrap
├── config/
│   ├── app.php                           # App configuration
│   ├── auth.php                          # Authentication configuration
│   ├── database.php                      # Database configuration
│   ├── sanctum.php                       # Sanctum configuration
│   └── token.php                         # Custom token TTL config
├── database/
│   ├── factories/                        # Model factories for testing
│   ├── migrations/                       # Database migrations
│   └── seeders/                          # Database seeders
├── routes/
│   ├── api.php                           # API routes definition
│   └── web.php                           # Web routes (minimal)
├── tests/
│   ├── Feature/
│   │   └── Http/
│   │       └── Controllers/              # Controller integration tests
│   └── Unit/
│       ├── Models/                       # Model unit tests
│       └── Services/                     # Service unit tests (pending)
├── composer.json                         # PHP dependencies
├── phpunit.xml                           # PHPUnit configuration
└── .env                                  # Environment configuration
```

---

## Core Concepts

### 1. **Multi-Tenancy**
Every resource (Category, Transaction, Budget) is owned by a specific user via `user_id` foreign key. Users can only access their own data.

**Implementation:**
- `user_id` column on all resource tables
- Eloquent relationships: `User hasMany Categories/Transactions/Budgets`
- `AuthorizeUser` middleware validates ownership before controller execution

### 2. **RESTful API Design**
All endpoints follow REST conventions:
- `GET /api/resource` - List all (with pagination)
- `POST /api/resource` - Create new
- `GET /api/resource/{id}` - Show single
- `PUT/PATCH /api/resource/{id}` - Update
- `DELETE /api/resource/{id}` - Delete

### 3. **JSON-Only Responses**
The API **always** returns JSON, even for validation errors:
- `ForceJsonResponse` middleware ensures `Accept: application/json`
- `ApiRequest` base class forces JSON responses for validation failures
- No redirects, no HTML responses

### 4. **Soft Deletes**
Categories and Transactions use soft deletes:
- `deleted_at` column instead of actual deletion
- Preserves historical data and relationships
- Queries automatically exclude soft-deleted records (unless specifically included)

### 5. **Date Handling**
- **Transaction dates**: Stored as `DATE` type (no time component)
- **User timezone**: Stored in `users.timezone` (IANA format: `America/New_York`)
- **Default date**: If transaction date not provided, uses "today" in user's timezone
- **Audit timestamps**: `created_at`, `updated_at` are `TIMESTAMP` in UTC

---

## Authentication & Authorization

### Authentication: Laravel Sanctum

**Token-based authentication** without traditional session management.

#### Registration Flow:
```php
POST /api/register
Body: { name, email, password, password_confirmation, timezone? }

Response 201:
{
  "user": { id, name, email, timezone },
  "token": "1|abc123...",
  "expires_at": "2025-11-14T10:00:00Z",
  "expires_in": 3600
}
```

**Implementation Details:**
- Uses `AuthController::issueToken()` helper
- Token TTL configured in `config/token.php` (default: 60 minutes)
- Token includes expiration metadata
- Password hashed with `Hash::make()`

#### Login Flow:
```php
POST /api/login
Body: { email, password }

Response 200: (same as registration)
```

**Implementation Details:**
- Validates credentials with `Hash::check()`
- Throws `ValidationException` if credentials invalid
- Returns new token on success

#### Token Refresh:
```php
POST /api/refresh
Headers: Authorization: Bearer {old_token}

Response 200:
{
  "token": "2|xyz789...",
  "expires_at": "...",
  "expires_in": 3600
}
```

**Implementation Details:**
- Deletes old token (`$request->user()->currentAccessToken()->delete()`)
- Issues new token with fresh expiration
- **Token rotation** for enhanced security

#### Logout:
```php
POST /api/logout
Headers: Authorization: Bearer {token}

Response 204: No Content
```

**Implementation Details:**
- Deletes current token only
- User can have multiple tokens (multiple devices)
- Doesn't log out other sessions

### Authorization: Custom Middleware

**Authorization is handled by `AuthorizeUser` middleware**, NOT Laravel Policies.

#### How It Works:
```php
// Applied to resource routes
Route::apiResource('budgets', BudgetController::class)
    ->middleware('owner:budget');
```

**Middleware Logic:**
1. Extracts model from route binding (e.g., `$budget`)
2. Checks if model has `user_id` or `owner_id` property
3. Compares with authenticated user's ID
4. Returns 403 if mismatch, continues if match

**Key Points:**
- Runs BEFORE controller method
- No `$this->authorize()` calls in controllers
- Automatically handles `index()` and `store()` (no model to check)
- Validates `show()`, `update()`, `destroy()` (model exists)

**Error Responses:**
- `401 Unauthorized` - No authentication token
- `403 Forbidden` - Token valid but not the owner
- `404 Not Found` - Resource doesn't exist

---

## Data Models

### 1. User Model

**Table:** `users`

**Columns:**
- `id` (bigint, PK)
- `name` (varchar 255)
- `email` (varchar 255, unique)
- `password` (varchar 255, hashed)
- `timezone` (varchar 255, nullable) - IANA timezone
- `email_verified_at` (timestamp, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)
- `deleted_at` (timestamp, nullable) - Soft delete

**Relationships:**
```php
User hasMany Category
User hasMany Transaction
User hasMany Budget
```

**Features:**
- Implements `HasApiTokens` trait (Sanctum)
- Uses `SoftDeletes` trait
- Password hidden from JSON responses
- `owned()` scope for querying user's resources

**Key Methods:**
```php
$user->categories // Get all categories
$user->transactions // Get all transactions
$user->budgets // Get all budgets
```

---

### 2. Category Model

**Table:** `categories`

**Columns:**
- `id` (bigint, PK)
- `user_id` (bigint, FK → users.id)
- `name` (varchar 255)
- `icon` (varchar 255, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)
- `deleted_at` (timestamp, nullable)

**Relationships:**
```php
Category belongsTo User
Category hasMany Transaction
Category hasMany Budget
```

**Validation Rules:**
- `name` must be unique per user (not globally)
- `icon` is optional string

**Soft Delete Behavior:**
- Soft-deleted categories remain in transactions (historical integrity)
- Validation prevents using soft-deleted categories for new transactions
- Foreign key is intentionally NOT enforced on transactions.category_id

---

### 3. Transaction Model

**Table:** `transactions`

**Columns:**
- `id` (bigint, PK)
- `user_id` (bigint, FK → users.id)
- `category_id` (bigint, reference to categories.id - NO FK constraint)
- `amount` (decimal 10,2)
- `description` (varchar 255, nullable)
- `date` (date) - The transaction date in user's perspective
- `created_at` (timestamp)
- `updated_at` (timestamp)
- `deleted_at` (timestamp, nullable)

**Relationships:**
```php
Transaction belongsTo User
Transaction belongsTo Category
```

**Key Points:**
- `amount` stored as positive decimal (no negative for expenses)
- `date` is DATE type (not timestamp) - matches user's calendar
- No `date_local` or `occurred_at_utc` fields (simplified design)
- Soft deletes preserve spending history

**Validation:**
- `amount` required, minimum 0.01
- `date` optional (defaults to today in user's timezone)
- `category_id` optional but must exist and belong to user

---

### 4. Budget Model

**Table:** `budgets`

**Columns:**
- `id` (bigint, PK)
- `user_id` (bigint, FK → users.id)
- `category_id` (bigint, FK → categories.id, nullable)
- `limit` (decimal 10,2) - Spending limit
- `period` (enum: 'monthly', 'yearly')
- `start_date` (date)
- `end_date` (date, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Relationships:**
```php
Budget belongsTo User
Budget belongsTo Category
```

**Key Features:**
- **Automatic end_date calculation**: If not provided, computed from `start_date` + `period`
- **Progress tracking**: Spent amount calculated dynamically via BudgetService
- **Category-specific**: Each budget tracks one category
- **Non-recurring**: Budgets don't auto-renew (user creates new ones)

**Validation:**
- `category_id` required, must exist and belong to user
- `limit` required, minimum 0
- `period` required, must be 'monthly' or 'yearly'
- `start_date` required
- `end_date` optional, must be >= start_date

**Important Restriction:**
- `category_id` **cannot be changed** after creation (enforced in BudgetService)

---

## Request Validation

### Base Class: ApiRequest

All FormRequests extend `ApiRequest`:

```php
abstract class ApiRequest extends FormRequest
{
    public function expectsJson(): bool { return true; }
    public function wantsJson(): bool { return true; }
}
```

**Purpose:** Forces JSON responses for validation errors (returns 422, not redirect)

### Validation Pattern

Every controller action that accepts input has a dedicated FormRequest:

**Example: BudgetStoreRequest**

```php
class BudgetStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Must be authenticated
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn($query) => $query->where('user_id', $this->user()->id))
                    ->whereNull('deleted_at'),
            ],
            'limit' => ['required', 'numeric', 'min:0'],
            'period' => ['required', 'in:monthly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Auto-calculate end_date if not provided
        if (!$this->filled('end_date') && $this->filled('start_date')) {
            $this->merge([
                'end_date' => $this->computeEndDate(
                    $this->input('start_date'),
                    $this->input('period', 'monthly')
                ),
            ]);
        }
    }
}
```

**Key Features:**
1. **Authorization**: Returns true/false (not handled by policies anymore)
2. **Rules**: Laravel validation rules with custom database checks
3. **prepareForValidation()**: Transforms/enriches data before validation
4. **Automatic 422 response**: Laravel handles validation failures

### Update Requests Pattern

Update requests are more permissive:

```php
class BudgetUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'limit' => ['sometimes', 'numeric', 'min:0'],
            'period' => ['sometimes', 'in:monthly,yearly'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Only allow specific fields to be updated
        $updatable = ['limit', 'period', 'start_date', 'end_date'];
        return array_intersect_key($validated, array_flip($updatable));
    }
}
```

**Key Differences:**
- Rules use `sometimes` (optional)
- `validated()` override filters to only updatable fields
- Returns 422 if empty payload (at least one field required)

---

## Business Logic Layer

### BudgetService

**Purpose:** Encapsulates complex budget-related business logic

**Location:** `app/Services/BudgetService.php`

**Methods:**

#### 1. createBudgetForUser()
```php
public function createBudgetForUser(User $user, array $payload): Budget
```
- Creates budget via user relationship (auto-sets user_id)
- Returns refreshed budget instance
- Called from BudgetController::store()

#### 2. updateBudget()
```php
public function updateBudget(Budget $budget, array $payload): Budget
```
- Updates budget with validated data
- **Prevents category_id changes** (business rule)
- Returns refreshed budget instance

#### 3. deleteBudget()
```php
public function deleteBudget(Budget $budget): void
```
- Hard deletes budget (no soft delete)
- Simple wrapper for consistency

#### 4. getBudgetProgress()
```php
public function getBudgetProgress(Budget $budget): array
```
**Returns:**
```php
[
    'limit' => 500.00,
    'spent' => 350.50,
    'remaining' => 149.50,
    'progress_percent' => 70.10,
    'is_over_budget' => false
]
```

**Algorithm:**
1. Queries transactions for the budget's user and category
2. Filters by date range (start_date to end_date)
3. Sums transaction amounts
4. Calculates remaining = limit - spent
5. Calculates percentage = (spent / limit) * 100
6. Determines if over budget

**Performance Note:**
- N+1 query issue avoided by eager loading user relationship in controller
- Uses `whereDate()` for efficient date range queries

#### 5. calculateSpentForRange() (protected)
```php
protected function calculateSpentForRange(Budget $budget): float
```
- Helper method for getBudgetProgress()
- Handles the actual transaction query and sum

---

## API Resources

### Purpose
Transform Eloquent models into consistent JSON responses.

### Pattern

**Simple Resource:**
```php
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**Resource with Computed Data:**
```php
class BudgetResource extends JsonResource
{
    protected $progressStats;

    public function __construct($resource, $progressStats = null)
    {
        parent::__construct($resource);
        $this->progressStats = $progressStats; // Injected from controller
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
            ] : null,
            'limit' => (float) $this->limit,
            'period' => $this->period,
            'range' => [
                'start' => $this->start_date?->format('Y-m-d'),
                'end' => $this->end_date?->format('Y-m-d'),
            ],
        ];

        // Conditionally add stats if provided
        if (is_array($this->progressStats)) {
            $data['stats'] = [
                'spent' => (float) $this->progressStats['spent'],
                'remaining' => (float) $this->progressStats['remaining'],
                'progress_percent' => (float) $this->progressStats['progress_percent'],
                'over' => (bool) $this->progressStats['is_over_budget'],
            ];
        }

        return $data;
    }
}
```

**Usage in Controller:**
```php
public function show(Budget $budget, BudgetService $budgetService): BudgetResource
{
    $budget->load('category'); // Eager load relationship
    $stats = $budgetService->getBudgetProgress($budget);
    
    return new BudgetResource($budget, $stats); // Inject stats
}
```

### Resource Collections

**Pattern:**
```php
class CategoryCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
```

**Usage:**
```php
return new CategoryCollection($categories); // Wraps in { "data": [...] }
```

---

## Middleware Pipeline

### 1. ForceJsonResponse

**File:** `app/Http/Middleware/ForceJsonResponse.php`

**Purpose:** Ensures all API responses are JSON (no redirects)

**Applied to:** All API routes (prepended in bootstrap/app.php)

**Implementation:**
```php
$request->headers->set('Accept', 'application/json');
```

---

### 2. auth:sanctum

**Built-in Sanctum middleware**

**Purpose:** Validates Bearer token and loads authenticated user

**Applied to:** All protected routes in `Route::middleware('auth:sanctum')` group

**Behavior:**
- Returns 401 if no token or invalid token
- Sets `$request->user()` if token valid
- Checks token expiration

---

### 3. owner (AuthorizeUser)

**File:** `app/Http/Middleware/AuthorizeUser.php`

**Purpose:** Validates resource ownership (replaces policies)

**Applied to:** Individual resource routes
```php
Route::apiResource('budgets', BudgetController::class)
    ->middleware('owner:budget');
```

**Algorithm:**
1. Extract parameter name from middleware (e.g., 'budget')
2. Get model instance from route binding (`$request->budget`)
3. If no model (index/store), continue (no ownership check needed)
4. If model exists:
   - Check if user authenticated (401 if not)
   - Get owner ID (`$model->user_id` or `$model->owner_id`)
   - Compare with `$request->user()->id`
   - Return 403 if mismatch
5. Continue to controller if match

**Key Benefits:**
- DRY: No repeated `$this->authorize()` in every controller method
- Consistent: Same logic across all resources
- Automatic: Handles index/store (no check) vs show/update/destroy (check)

---

## Testing Strategy

### Test Structure

```
tests/
├── Feature/
│   └── Http/
│       └── Controllers/
│           ├── AuthControllerTest.php          # 47 tests
│           ├── BudgetControllerTest.php        # 41 tests
│           ├── CategoryControllerTest.php      # 32 tests
│           └── TransactionControllerTest.php   # 48 tests
└── Unit/
    └── Models/
        ├── BudgetTest.php                      # 21 tests
        ├── CategoryTest.php                    # 17 tests
        ├── TransactionTest.php                 # 19 tests
        └── UserTest.php                        # 21 tests
```

**Total:** 247 tests passing (731 assertions)

### Test Naming Convention

```php
public function test_{action}_{scenario}_and_returns_{status_code}()
```

**Examples:**
```php
test_store_creates_budget_for_authenticated_user()
test_update_by_non_owner_returns_403()
test_destroy_without_authentication_returns_401()
```

### Feature Test Pattern

**Setup:**
```php
use RefreshDatabase, WithFaker;

protected function setUp(): void
{
    parent::setUp();
    $this->user = User::factory()->create();
}
```

**Authentication Helper:**
```php
protected function actingAsUser(User $user = null)
{
    $user = $user ?? $this->user;
    return $this->actingAs($user, 'sanctum');
}
```

**Typical Test:**
```php
public function test_store_creates_category_for_authenticated_user()
{
    $category = Category::factory()->make();
    
    $response = $this->actingAsUser()
        ->postJson('/api/categories', [
            'name' => $category->name,
            'icon' => $category->icon,
        ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'user_id', 'name', 'icon']
        ]);
    
    $this->assertDatabaseHas('categories', [
        'name' => $category->name,
        'user_id' => $this->user->id,
    ]);
}
```

**Authorization Test:**
```php
public function test_show_for_non_owner_returns_403()
{
    $otherUser = User::factory()->create();
    $category = Category::factory()->for($otherUser)->create();
    
    $response = $this->actingAsUser() // Authenticated as different user
        ->getJson("/api/categories/{$category->id}");
    
    $response->assertStatus(403);
}
```

### Unit Test Pattern

**Model Tests:**
```php
public function test_user_has_many_categories()
{
    $user = User::factory()->create();
    $categories = Category::factory()->count(3)->for($user)->create();
    
    $this->assertInstanceOf(HasMany::class, $user->categories());
    $this->assertCount(3, $user->categories);
}

public function test_category_casts_dates_correctly()
{
    $category = Category::factory()->create();
    
    $this->assertInstanceOf(Carbon::class, $category->created_at);
    $this->assertInstanceOf(Carbon::class, $category->updated_at);
}
```

### Running Tests

```bash
# All tests
php artisan test

# Specific test file
php artisan test --filter=BudgetControllerTest

# Specific test method
php artisan test --filter=test_store_creates_budget

# With coverage (if configured)
php artisan test --coverage
```

---

## Design Patterns

### 1. Repository Pattern (Partial)

**Service Layer** acts as repository for complex operations:
- `BudgetService` encapsulates budget logic
- Controllers remain thin, delegating to services
- Future: Can add `TransactionService`, `CategoryService`

### 2. Resource Pattern

**API Resources** transform models into consistent JSON:
- Single source of truth for API response structure
- Handles nested relationships
- Can inject computed data (e.g., budget stats)

### 3. Form Request Pattern

**Validation encapsulation:**
- Each action has dedicated FormRequest
- Validation logic separate from controllers
- Can transform data before validation (`prepareForValidation`)

### 4. Middleware Pattern

**Authorization middleware:**
- Centralizes ownership checks
- Reduces duplication across controllers
- Configurable via route definitions

### 5. Factory Pattern

**Model Factories for testing:**
- Consistent test data generation
- Supports relationships
- Faker integration for realistic data

### 6. Scope Pattern

**Query Scopes in models:**
```php
public function scopeOwned($query, $user = null)
{
    $user = $user ?: auth()->user();
    return $query->where('user_id', $user->id);
}

// Usage: Budget::owned()->get()
```

---

## Common Workflows

### Creating a New Transaction

**1. Client Request:**
```json
POST /api/transactions
Authorization: Bearer {token}
Content-Type: application/json

{
  "category_id": 5,
  "amount": 45.99,
  "description": "Weekly groceries",
  "date": "2025-11-13"
}
```

**2. Middleware Pipeline:**
- `ForceJsonResponse`: Sets Accept header
- `auth:sanctum`: Validates token, loads user
- `owner:transaction`: Skipped (no existing model for store)

**3. Controller:**
```php
public function store(TransactionStoreRequest $request): TransactionResource
{
    $user = $request->user();
    $payload = $request->validated(); // After validation
    $transaction = $user->transactions()->create($payload);
    return new TransactionResource($transaction);
}
```

**4. Validation (TransactionStoreRequest):**
- `prepareForValidation()`: If no date, defaults to today
- `rules()`: Validates amount, date, category_id
- Checks category exists and belongs to user
- Returns 422 if validation fails

**5. Response:**
```json
201 Created
{
  "data": {
    "id": 123,
    "user_id": 1,
    "category_id": 5,
    "amount": "45.99",
    "description": "Weekly groceries",
    "date": "2025-11-13",
    "created_at": "2025-11-13T10:00:00Z",
    "updated_at": "2025-11-13T10:00:00Z"
  }
}
```

---

### Viewing Budget Progress

**1. Client Request:**
```json
GET /api/budgets/42
Authorization: Bearer {token}
```

**2. Middleware Pipeline:**
- `ForceJsonResponse`: Sets Accept header
- `auth:sanctum`: Validates token, loads user
- `owner:budget`: Checks budget.user_id == user.id

**3. Controller:**
```php
public function show(Budget $budget, BudgetService $budgetService): BudgetResource
{
    $budget->load('category'); // Eager load
    $stats = $budgetService->getBudgetProgress($budget);
    return new BudgetResource($budget, $stats);
}
```

**4. BudgetService::getBudgetProgress():**
- Queries transactions for user, category, date range
- Sums amounts
- Calculates spent, remaining, percentage, over status
- Returns stats array

**5. BudgetResource:**
- Transforms budget model
- Merges in stats
- Formats dates
- Nests category data

**6. Response:**
```json
200 OK
{
  "data": {
    "id": 42,
    "user_id": 1,
    "category": {
      "id": 5,
      "name": "Groceries",
      "icon": "🛒"
    },
    "limit": 500.00,
    "period": "monthly",
    "range": {
      "start": "2025-11-01",
      "end": "2025-11-30"
    },
    "stats": {
      "spent": 350.50,
      "remaining": 149.50,
      "progress_percent": 70.10,
      "over": false
    }
  }
}
```

---

## Adding New Features

### Step-by-Step Guide

#### Example: Adding a "Tags" feature for transactions

**1. Database Migration**

```bash
php artisan make:migration create_tags_table
php artisan make:migration create_transaction_tag_pivot_table
```

```php
// create_tags_table.php
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('color')->nullable();
    $table->timestamps();
    
    $table->unique(['user_id', 'name']); // Unique per user
});

// create_transaction_tag_pivot_table.php
Schema::create('transaction_tag', function (Blueprint $table) {
    $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->primary(['transaction_id', 'tag_id']);
});
```

**2. Model**

```bash
php artisan make:model Tag
```

```php
// app/Models/Tag.php
class Tag extends Model
{
    use HasFactory;
    
    protected $fillable = ['user_id', 'name', 'color'];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function transactions()
    {
        return $this->belongsToMany(Transaction::class);
    }
    
    public function scopeOwned($query, $user = null)
    {
        $user = $user ?: auth()->user();
        return $query->where('user_id', $user->id);
    }
}

// Add to Transaction.php
public function tags()
{
    return $this->belongsToMany(Tag::class);
}

// Add to User.php
public function tags()
{
    return $this->hasMany(Tag::class);
}
```

**3. Controller**

```bash
php artisan make:controller TagController --api
```

```php
// app/Http/Controllers/TagController.php
class TagController extends Controller
{
    public function index(Request $request)
    {
        $tags = $request->user()->tags;
        return TagResource::collection($tags);
    }
    
    public function store(TagStoreRequest $request)
    {
        $tag = $request->user()->tags()->create($request->validated());
        return new TagResource($tag);
    }
    
    public function show(Tag $tag)
    {
        return new TagResource($tag);
    }
    
    public function update(TagUpdateRequest $request, Tag $tag)
    {
        $tag->update($request->validated());
        return new TagResource($tag);
    }
    
    public function destroy(Tag $tag)
    {
        $tag->delete();
        return response()->noContent();
    }
}
```

**4. Form Requests**

```bash
php artisan make:request TagStoreRequest
php artisan make:request TagUpdateRequest
```

```php
// app/Http/Requests/TagStoreRequest.php
class TagStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tags', 'name')
                    ->where('user_id', $this->user()->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
        ];
    }
}

// app/Http/Requests/TagUpdateRequest.php
class TagUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('tags', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('tag')->id),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
        ];
    }
}
```

**5. API Resource**

```bash
php artisan make:resource TagResource
```

```php
// app/Http/Resources/TagResource.php
class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'transaction_count' => $this->whenLoaded('transactions', 
                fn() => $this->transactions->count()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**6. Routes**

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('tags', TagController::class)
        ->middleware('owner:tag');
    
    // Attach/detach tags to transactions
    Route::post('/transactions/{transaction}/tags/{tag}', 
        [TransactionController::class, 'attachTag'])
        ->middleware('owner:transaction');
        
    Route::delete('/transactions/{transaction}/tags/{tag}', 
        [TransactionController::class, 'detachTag'])
        ->middleware('owner:transaction');
});
```

**7. Factory for Testing**

```bash
php artisan make:factory TagFactory
```

```php
// database/factories/TagFactory.php
class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
```

**8. Tests**

```bash
php artisan make:test Feature/Http/Controllers/TagControllerTest
php artisan make:test Unit/Models/TagTest --unit
```

```php
// tests/Feature/Http/Controllers/TagControllerTest.php
class TagControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    protected User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }
    
    public function test_index_returns_only_authenticated_users_tags()
    {
        Tag::factory()->count(3)->for($this->user)->create();
        $otherUser = User::factory()->create();
        Tag::factory()->count(2)->for($otherUser)->create();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tags');
        
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }
    
    public function test_store_creates_tag_for_authenticated_user()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/tags', [
                'name' => 'Business',
                'color' => '#FF5733',
            ]);
        
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Business');
        
        $this->assertDatabaseHas('tags', [
            'user_id' => $this->user->id,
            'name' => 'Business',
        ]);
    }
    
    public function test_show_for_non_owner_returns_403()
    {
        $otherUser = User::factory()->create();
        $tag = Tag::factory()->for($otherUser)->create();
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tags/{$tag->id}");
        
        $response->assertStatus(403);
    }
    
    // Add more tests following existing patterns...
}
```

**9. Update Transaction Tests**

Update `TransactionStoreRequest` and `TransactionUpdateRequest` to accept `tag_ids`:

```php
public function rules(): array
{
    return [
        // ... existing rules
        'tag_ids' => ['sometimes', 'array'],
        'tag_ids.*' => [
            'integer',
            Rule::exists('tags', 'id')
                ->where('user_id', $this->user()->id),
        ],
    ];
}
```

Update controller to sync tags:

```php
public function store(TransactionStoreRequest $request): TransactionResource
{
    $user = $request->user();
    $payload = $request->validated();
    $tagIds = $payload['tag_ids'] ?? [];
    unset($payload['tag_ids']);
    
    $transaction = $user->transactions()->create($payload);
    
    if (!empty($tagIds)) {
        $transaction->tags()->sync($tagIds);
    }
    
    $transaction->load('tags');
    return new TransactionResource($transaction);
}
```

**10. Run Tests**

```bash
php artisan test --filter=TagControllerTest
php artisan test --filter=TagTest
```

---

### Checklist for New Features

- [ ] **Database:** Migration with proper indexes and foreign keys
- [ ] **Model:** Eloquent model with relationships, fillable, casts
- [ ] **Factory:** For testing with realistic data
- [ ] **Controller:** Thin layer, delegates to services if complex
- [ ] **FormRequests:** Validation for store and update actions
- [ ] **Resource:** JSON transformer for consistent API responses
- [ ] **Routes:** RESTful routes with middleware
- [ ] **Middleware:** Apply `owner` middleware if resource-specific
- [ ] **Service:** If business logic is complex (like BudgetService)
- [ ] **Tests:** Feature tests for all endpoints + authorization
- [ ] **Tests:** Unit tests for model relationships and methods
- [ ] **Documentation:** Update API_ENDPOINTS.md with new routes

---

## Configuration Files

### config/token.php
```php
return [
    'ttl_minutes' => env('TOKEN_TTL_MINUTES', 60),
];
```
Controls token expiration time.

### config/app.php
```php
'timezone' => env('APP_TIMEZONE', 'UTC'),
```
Default timezone for the application.

### .env
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fintrack
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=
SESSION_DRIVER=database

TOKEN_TTL_MINUTES=60
```

---

## Key Implementation Details

### 1. Why No Policies?

**Original Design:** Used Laravel Policies with `$this->authorize()` in controllers

**Current Design:** Custom `AuthorizeUser` middleware

**Reasons for Change:**
- DRY principle: Avoid repeating `$this->authorize()` in every method
- Centralized: Single middleware handles all ownership checks
- Automatic: Works for all resources with `user_id` column
- Simpler: Less code, easier to maintain

### 2. Why BudgetService?

**Reasons:**
- Budget progress calculation is complex (query + math)
- Used in multiple places (index, show, update)
- Future extensibility (notifications, alerts, recurring budgets)
- Testable: Can unit test service independently

**When to Create Services:**
- Logic spans multiple models
- Complex calculations or business rules
- Reused across multiple controllers
- Needs to be tested independently

### 3. Why ApiRequest Base Class?

**Problem:** Laravel validates and redirects by default (for web apps)

**Solution:** Force JSON responses for API

**Implementation:**
```php
public function expectsJson(): bool { return true; }
public function wantsJson(): bool { return true; }
```

**Result:** Always returns 422 JSON, never redirects

### 4. Why Soft Deletes for Some Models?

**Soft Deleted:**
- Categories (preserve transaction history)
- Transactions (preserve budget calculations)
- Users (preserve all data, compliance)

**Hard Deleted:**
- Budgets (no dependencies, can recreate)

**Reason:** Historical data integrity and audit trails

### 5. Why No date_local or occurred_at_utc?

**Original Design:** Multiple date fields for precision

**Current Design:** Single `date` field (DATE type)

**Reasons:**
- Users think in days, not timestamps
- Simpler queries: `WHERE date BETWEEN x AND y`
- No timezone math needed for budget calculations
- Single source of truth (no sync issues)
- Audit timestamps (created_at, updated_at) for system tracking

---

## Database Schema Diagram

```
┌─────────────────────────┐
│         users           │
├─────────────────────────┤
│ id (PK)                 │
│ name                    │
│ email (unique)          │
│ password                │
│ timezone                │
│ created_at              │
│ updated_at              │
│ deleted_at              │
└─────────────────────────┘
           │
           │ 1:N
           ├─────────────────────────────────┐
           │                                 │
           ▼                                 ▼
┌─────────────────────────┐       ┌─────────────────────────┐
│      categories         │       │     transactions        │
├─────────────────────────┤       ├─────────────────────────┤
│ id (PK)                 │       │ id (PK)                 │
│ user_id (FK)            │◀──────│ category_id (no FK)     │
│ name                    │       │ user_id (FK)            │
│ icon                    │       │ amount                  │
│ created_at              │       │ description             │
│ updated_at              │       │ date                    │
│ deleted_at              │       │ created_at              │
└─────────────────────────┘       │ updated_at              │
           │                      │ deleted_at              │
           │                      └─────────────────────────┘
           │ 1:N                            ▲
           │                                │
           ▼                                │ N:1
┌─────────────────────────┐                │
│        budgets          │                │
├─────────────────────────┤                │
│ id (PK)                 │                │
│ user_id (FK)            │                │
│ category_id (FK)        │────────────────┘
│ limit                   │
│ period                  │
│ start_date              │
│ end_date                │
│ created_at              │
│ updated_at              │
└─────────────────────────┘

┌─────────────────────────────────────────────────────┐
│             personal_access_tokens                  │
├─────────────────────────────────────────────────────┤
│ id (PK)                                             │
│ tokenable_type                                      │
│ tokenable_id                                        │
│ name                                                │
│ token (unique)                                      │
│ abilities                                           │
│ expires_at                                          │
│ last_used_at                                        │
│ created_at                                          │
│ updated_at                                          │
└─────────────────────────────────────────────────────┘
```

---

## Error Handling

### Validation Errors (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["The amount must be at least 0.01."],
    "category_id": ["The selected category is invalid."]
  }
}
```

### Authentication Errors (401)
```json
{
  "message": "Unauthenticated."
}
```

### Authorization Errors (403)
```json
{
  "message": "Unauthorised access"
}
```

### Not Found Errors (404)
```json
{
  "message": "No query results for model [App\\Models\\Budget] 999"
}
```

### Server Errors (500)
```json
{
  "message": "Server Error"
}
```

---

## Best Practices in This Codebase

1. **Always use FormRequests** for validation (never validate in controllers)
2. **Always eager load relationships** before returning resources (avoid N+1)
3. **Always use factories** in tests (never create() directly unless necessary)
4. **Always check authorization** via middleware (not in controllers)
5. **Always use API Resources** for responses (never return models directly)
6. **Always type-hint dependencies** for service injection
7. **Always test both success and failure paths**
8. **Always include status code assertions** in tests
9. **Always use soft deletes** for data that affects history
10. **Always return proper HTTP status codes** (201 for create, 204 for delete, etc.)

---

## Common Gotchas

1. **Category validation checks soft deletes:**
   ```php
   Rule::exists('categories', 'id')
       ->where('user_id', $this->user()->id)
       ->whereNull('deleted_at') // Important!
   ```

2. **Budget category_id cannot be updated:**
   ```php
   // BudgetService::updateBudget
   if (array_key_exists('category_id', $payload)) {
       unset($payload['category_id']); // Silently ignored
   }
   ```

3. **Empty update payloads are rejected:**
   ```php
   PATCH /api/budgets/1 with {}
   // Returns 422: At least one field required
   ```

4. **Token refresh invalidates old token:**
   ```php
   POST /api/refresh
   // Old token becomes invalid immediately
   ```

5. **Middleware runs BEFORE controller:**
   ```php
   // AuthorizeUser checks ownership
   // Then controller executes
   // No need for $this->authorize() calls
   ```

---

## Future Enhancements (Not Implemented)

- **Budget auto-renewal:** Scheduled job to create new budgets
- **Budget alerts:** Notify when approaching/exceeding limit
- **Transaction attachments:** Upload receipts/invoices
- **Multi-currency support:** Handle different currencies
- **Recurring transactions:** Auto-create monthly bills
- **Budget templates:** Reusable budget configurations
- **Reporting:** Analytics and spending insights
- **Export data:** CSV/PDF export functionality
- **Two-factor authentication:** Enhanced security
- **Email verification:** Verify user emails

---

## Conclusion

This document provides a complete architectural overview of FinTrack. When adding new features:

1. Follow the existing patterns (FormRequests, Resources, Middleware)
2. Write tests first (TDD approach)
3. Keep controllers thin
4. Use services for complex logic
5. Maintain data isolation (multi-tenancy)
6. Update this documentation

For specific implementation questions, refer to existing code as examples:
- **Simple CRUD:** See CategoryController
- **Complex logic:** See BudgetController + BudgetService
- **Authentication:** See AuthController
- **Testing:** See tests/Feature/Http/Controllers/

**Remember:** The codebase values simplicity, testability, and maintainability over clever abstractions.
