# FinTrack Code Architecture

## Layer Architecture

```
┌─────────────────────────────────────┐
│         Routes (api.php)            │  ← Entry points
├─────────────────────────────────────┤
│         Middleware                  │  ← Authentication, CORS
├─────────────────────────────────────┤
│         Controllers                 │  ← Request handling
├─────────────────────────────────────┤
│     Form Requests (Validation)      │  ← Input validation
├─────────────────────────────────────┤
│         Policies                    │  ← Authorization
├─────────────────────────────────────┤
│         Services                    │  ← Business logic
├─────────────────────────────────────┤
│         Models                      │  ← Data access
├─────────────────────────────────────┤
│         Resources                   │  ← Response formatting
└─────────────────────────────────────┘
```

---

## Controllers

Location: `app/Http/Controllers/`

### AuthController

**Purpose**: Handle user authentication

**Methods:**

-   `register(RegisterRequest)`: Create new user account
-   `login(LoginRequest)`: Authenticate and issue token
-   `logout()`: Revoke current token

**Dependencies:**

-   User model
-   Hash facade
-   Sanctum authentication

---

### CategoryController

**Purpose**: Manage user categories

**Methods:**

-   `index(Request)`: List user's categories (Collection)
-   `store(CategoryStoreRequest)`: Create category
-   `show(Category)`: Get single category (with authorization)
-   `update(CategoryUpdateRequest, Category)`: Update category
-   `destroy(Category)`: Soft delete category

**Pattern:**

```php
public function store(CategoryStoreRequest $request): CategoryResource
{
    // 1. Authorize (handled by Policy)
    // 2. Validate (handled by FormRequest)
    // 3. Create via relationship (auto-sets user_id)
    $category = $request->user()->categories()->create($request->validated());

    // 4. Return resource
    return new CategoryResource($category);
}
```

**Key Principle**: Controllers stay thin - delegate to policies, form requests, and models.

---

### TransactionController

**Purpose**: Manage user transactions

**Methods:**

-   `index(Request)`: List transactions (paginated, latest first)
-   `store(TransactionStoreRequest)`: Create transaction
-   `show(Transaction)`: Get single transaction
-   `update(TransactionUpdateRequest, Transaction)`: Update transaction (with refresh)
-   `destroy(Transaction)`: Soft delete transaction

**Special Notes:**

-   Always calls `->refresh()` after update to return latest data
-   Uses relationship to auto-set user_id
-   Implements soft deletes

---

### BudgetController

**Purpose**: Manage budgets with progress tracking

**Methods:**

-   `index(Request)`: List budgets (paginated)
-   `store(BudgetStoreRequest, BudgetService)`: Create budget
-   `show(Budget, BudgetService)`: Get budget with progress stats
-   `update(BudgetUpdateRequest, Budget, BudgetService)`: Update budget
-   `destroy(Budget, BudgetService)`: Delete budget

**Service Integration:**

```php
public function store(BudgetStoreRequest $request, BudgetService $budgetService): BudgetResource
{
    $this->authorize('create', Budget::class);

    $budget = $budgetService->createBudgetForUser(
        $request->user(),
        $request->validated()
    );

    return $this->buildBudgetResource($budget, $budgetService);
}

private function buildBudgetResource(Budget $budget, BudgetService $budgetService): BudgetResource
{
    $progressStats = $budgetService->getBudgetProgress($budget);
    return new BudgetResource($budget, $progressStats);
}
```

**Key Pattern**: Delegates to service layer for complex business logic.

---

## Form Requests

Location: `app/Http/Requests/`

### Purpose

-   Centralize validation logic
-   Keep controllers clean
-   Provide consistent error messages

### Common Pattern

```php
class CategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by Policy, so return true
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

### Key Form Requests

-   `CategoryStoreRequest`, `CategoryUpdateRequest`
-   `TransactionStoreRequest`, `TransactionUpdateRequest`
-   `BudgetStoreRequest`, `BudgetUpdateRequest`

### Update Request Pattern

Use `sometimes` for partial updates:

```php
public function rules(): array
{
    return [
        'name' => ['sometimes', 'string', 'max:255'],
        'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
    ];
}
```

### User Ownership Validation

```php
'category_id' => [
    'nullable',
    Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
]
```

---

## Policies

Location: `app/Policies/`

### Purpose

-   Enforce user ownership
-   Prevent unauthorized access
-   Centralize authorization logic

### Common Pattern

```php
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Controller scopes query
    }

    public function view(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function create(User $user): bool
    {
        return true; // Any authenticated user
    }

    public function update(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }
}
```

### Key Policies

-   `CategoryPolicy`
-   `TransactionPolicy`
-   `BudgetPolicy`

### Registration

Located in `app/Providers/AuthServiceProvider.php`

---

## Services

Location: `app/Services/`

### BudgetService

**Purpose**: Handle budget-specific business logic

**Methods:**

#### createBudgetForUser(User $user, array $payload): Budget

Creates budget and returns refreshed instance.

#### updateBudget(Budget $budget, array $payload): Budget

Updates budget and returns refreshed instance.

#### deleteBudget(Budget $budget): void

Deletes budget (hard delete, no soft delete for budgets).

#### getBudgetProgress(Budget $budget): array

Calculates spending progress against budget.

**Returns:**

```php
[
    'limit' => 500.00,
    'spent' => 145.50,
    'remaining' => 354.50,
    'progress_percent' => 29.10,
    'is_over_budget' => false
]
```

**Logic:**

1. Query transactions between start_date and end_date
2. Filter by user_id
3. Filter by category_id (if set)
4. Sum amounts
5. Calculate percentages
6. Determine if over budget

---

## Models

Location: `app/Models/`

### Common Traits

-   `HasFactory`: Factory support for testing
-   `SoftDeletes`: Non-destructive deletion (categories, transactions)

### User Model

```php
protected $fillable = ['name', 'email', 'password'];
protected $hidden = ['password', 'remember_token'];
protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
];

// Relationships
public function categories(): HasMany
public function transactions(): HasMany
public function budgets(): HasMany
```

### Category Model

```php
protected $fillable = ['user_id', 'name', 'icon'];

// Relationships
public function user(): BelongsTo
public function transactions(): HasMany
public function budgets(): HasMany
```

### Transaction Model

```php
protected $fillable = [
    'user_id', 'category_id', 'amount', 'description', 'date'
];
protected $casts = [
    'amount' => 'decimal:2',
    'date' => 'date',
];

// Relationships
public function user(): BelongsTo
public function category(): BelongsTo
```

### Budget Model

```php
protected $fillable = [
    'user_id', 'category_id', 'limit', 'period',
    'start_date', 'end_date'
];
protected $casts = [
    'limit' => 'decimal:2',
    'start_date' => 'date',
    'end_date' => 'date',
];

// Relationships
public function user(): BelongsTo
public function category(): BelongsTo
```

---

## Resources

Location: `app/Http/Resources/`

### Purpose

-   Consistent JSON formatting
-   Transform models for API responses
-   Hide sensitive fields

### Standard Resource

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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### Collection Resource

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

### Budget Resource (with extra data)

```php
class BudgetResource extends JsonResource
{
    protected $progressStats;

    public function __construct($resource, $progressStats = null)
    {
        parent::__construct($resource);
        $this->progressStats = $progressStats;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            // ... other fields
        ];

        if ($this->progressStats !== null) {
            $data['progress_stats'] = $this->progressStats;
        }

        return $data;
    }
}
```

---

## Middleware

### auth:sanctum

Applied to all protected API routes via route group.

**Location**: `routes/api.php`

```php
Route::middleware('auth:sanctum')->group(function () {
    // Protected routes
});
```

---

## Error Handling

### Global API Error Handler

**Location**: `bootstrap/app.php`

```php
$exceptions->renderable(function (Throwable $e, $request) {
    if ($request->is('api/*')) {
        $message = 'Server Error';
        $statusCode = 500;

        if ($e instanceof HttpException) {
            $message = $e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()];
            $statusCode = $e->getStatusCode();
        }

        if (config('app.debug')) {
            $message = $e->getMessage();
        }

        return response()->json(['message' => $message], $statusCode);
    }
});
```

**Handles:**

-   401 Unauthorized
-   403 Forbidden
-   404 Not Found
-   422 Validation Errors (automatic)
-   500 Server Errors

---

## Best Practices

1. **Controllers**: Thin, delegate to services/policies
2. **Validation**: Always use Form Requests
3. **Authorization**: Always check via Policies
4. **User Scoping**: Use relationships to auto-set user_id
5. **Responses**: Always use Resources for consistency
6. **Refresh**: Call `->refresh()` after updates
7. **Soft Deletes**: Prefer over hard deletes
8. **Services**: Extract complex logic from controllers
9. **Testing**: Write feature tests for all endpoints
10. **Type Hints**: Use return types for clarity
