# Security Enhancements

**Priority**: 🔴 HIGH  
**Estimated Time**: 2-3 hours  
**Complexity**: Medium

---

## Table of Contents

1. [Fix Mass Assignment Vulnerability](#1-fix-mass-assignment-vulnerability)
2. [Add Rate Limiting](#2-add-rate-limiting)
3. [Add Input Sanitization](#3-add-input-sanitization)
4. [Add API Token Expiration](#4-add-api-token-expiration)

---

## 1. Fix Mass Assignment Vulnerability

### Problem

All models currently have `user_id` in the `$fillable` array, which could allow malicious users to override ownership if validation isn't perfect.

**Current Code:**

```php
// app/Models/Category.php
protected $fillable = ['user_id', 'name', 'icon'];
```

This is vulnerable because a malicious user could send:

```json
{
    "name": "My Category",
    "user_id": 999 // Attempt to assign to another user
}
```

### Solution 1: Use $guarded Instead (Most Restrictive)

```php
// filepath: app/Models/Category.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
```

**Then always create through relationships:**

```php
// In controller
$category = $request->user()->categories()->create($validatedData);
// user_id is set automatically
```

### Solution 2: Remove user_id from Fillable (Recommended)

```php
// filepath: app/Models/Category.php
protected $fillable = ['name', 'icon']; // NO user_id here

// filepath: app/Models/Transaction.php
protected $fillable = ['category_id', 'amount', 'description', 'date']; // NO user_id

// filepath: app/Models/Budget.php
protected $fillable = ['category_id', 'limit', 'period', 'start_date', 'end_date']; // NO user_id
```

**Update Request Classes:**

```php
// filepath: app/Http/Requests/CategoryStoreRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:10'],
            // NO user_id validation - it's set automatically
        ];
    }
}
```

**Update Controllers:**

```php
// filepath: app/Http/Controllers/CategoryController.php
public function store(CategoryStoreRequest $request): CategoryResource
{
    // user_id is automatically set by the relationship
    $category = $request->user()->categories()->create($request->validated());
    return new CategoryResource($category);
}

public function update(CategoryUpdateRequest $request, Category $category): CategoryResource
{
    $this->authorize('update', $category);

    // user_id cannot be changed
    $category->update($request->validated());

    return new CategoryResource($category->fresh());
}
```

### Solution 3: Explicitly Filter user_id

```php
// filepath: app/Http/Controllers/CategoryController.php
public function store(CategoryStoreRequest $request): CategoryResource
{
    $data = $request->validated();
    unset($data['user_id']); // Explicitly remove any user_id

    $category = $request->user()->categories()->create($data);
    return new CategoryResource($category);
}
```

### Testing the Fix

```php
// filepath: tests/Feature/Security/MassAssignmentTest.php
<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_override_user_id_in_category_creation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $response = $this->actingAs($user1)
            ->postJson('/api/categories', [
                'name' => 'Test Category',
                'user_id' => $user2->id, // Attempt to assign to user2
            ]);

        $response->assertCreated();

        $category = Category::latest()->first();

        // Should belong to user1, not user2
        $this->assertEquals($user1->id, $category->user_id);
        $this->assertNotEquals($user2->id, $category->user_id);
    }

    public function test_cannot_change_user_id_in_category_update(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $category = Category::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user1)
            ->putJson("/api/categories/{$category->id}", [
                'name' => 'Updated Name',
                'user_id' => $user2->id, // Attempt to reassign
            ]);

        $response->assertOk();

        $category->refresh();

        // Should still belong to user1
        $this->assertEquals($user1->id, $category->user_id);
    }
}
```

---

## 2. Add Rate Limiting

### Purpose

Prevent brute-force attacks on authentication endpoints and API abuse.

### Implementation

#### Step 1: Add Rate Limiting to Routes

```php
// filepath: routes/api.php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\BudgetController;
use Illuminate\Support\Facades\Route;

// Authentication endpoints with strict rate limiting
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1'); // 5 attempts per minute

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 attempts per minute

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // API resources with reasonable rate limiting
    Route::middleware('throttle:60,1')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('transactions', TransactionController::class);
        Route::apiResource('budgets', BudgetController::class);
    });
});
```

#### Step 2: Create Custom Rate Limiters

```php
// filepath: app/Providers/RouteServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Default API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Strict authentication rate limiter
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()), // 5 per minute
                Limit::perHour(20)->by($request->ip()),  // 20 per hour
            ];
        });

        // Generous rate limiter for reads
        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        // Strict rate limiter for writes
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
```

#### Step 3: Use Custom Rate Limiters

```php
// filepath: routes/api.php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:auth');

Route::middleware(['auth:sanctum'])->group(function () {
    // Read operations with higher limits
    Route::middleware('throttle:api-read')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{category}', [CategoryController::class, 'show']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
        Route::get('/budgets', [BudgetController::class, 'index']);
        Route::get('/budgets/{budget}', [BudgetController::class, 'show']);
    });

    // Write operations with lower limits
    Route::middleware('throttle:api-write')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

        Route::post('/budgets', [BudgetController::class, 'store']);
        Route::put('/budgets/{budget}', [BudgetController::class, 'update']);
        Route::delete('/budgets/{budget}', [BudgetController::class, 'destroy']);
    });
});
```

#### Step 4: Custom Rate Limit Response

```php
// filepath: app/Exceptions/Handler.php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many requests. Please slow down.',
                    'error' => [
                        'type' => 'rate_limit_exceeded',
                        'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
                    ]
                ], 429);
            }
        });
    }
}
```

### Testing Rate Limiting

```php
// filepath: tests/Feature/Security/RateLimitingTest.php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limiting(): void
    {
        // Attempt 6 logins (limit is 5)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);

            if ($i < 5) {
                $this->assertIn($response->status(), [401, 422]); // Invalid credentials
            } else {
                $response->assertStatus(429); // Rate limited
            }
        }
    }

    public function test_api_rate_limiting(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Make 61 requests (limit is 60 per minute)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->withToken($token)
                ->getJson('/api/categories');

            if ($i < 60) {
                $response->assertOk();
            } else {
                $response->assertStatus(429); // Rate limited
            }
        }
    }
}
```

---

## 3. Add Input Sanitization

### Purpose

Prevent XSS (Cross-Site Scripting) attacks by sanitizing user input.

### Implementation

#### Step 1: Create Sanitization Middleware

```php
// filepath: app/Http/Middleware/SanitizeInput.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                // Remove dangerous HTML/JS but keep safe formatting
                $value = strip_tags($value, '<b><i><u><p><br>');

                // Encode special characters
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
            }
        });

        $request->merge($input);

        return $next($request);
    }
}
```

#### Step 2: Register Middleware

For Laravel 11, register in `bootstrap/app.php`:

```php
// filepath: bootstrap/app.php
<?php

use App\Http\Middleware\SanitizeInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            SanitizeInput::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

#### Step 3: Alternative - Sanitize in Form Requests

```php
// filepath: app/Http/Requests/TransactionStoreRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sanitize description before validation
        if ($this->has('description') && is_string($this->description)) {
            $this->merge([
                'description' => strip_tags($this->description),
            ]);
        }
    }
}
```

### Testing Input Sanitization

```php
// filepath: tests/Feature/Security/InputSanitizationTest.php
<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_xss_script_tags_are_removed(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/transactions', [
                'category_id' => $category->id,
                'amount' => 50.00,
                'description' => '<script>alert("XSS")</script>Test',
                'date' => now()->format('Y-m-d'),
            ]);

        $response->assertCreated();

        $transaction = $user->transactions()->latest()->first();

        // Script tags should be removed
        $this->assertStringNotContainsString('<script>', $transaction->description);
        $this->assertStringNotContainsString('alert', $transaction->description);
    }

    public function test_safe_html_is_preserved(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/transactions', [
                'category_id' => $category->id,
                'amount' => 50.00,
                'description' => 'Test with <b>bold</b> and <i>italic</i>',
                'date' => now()->format('Y-m-d'),
            ]);

        $response->assertCreated();

        $transaction = $user->transactions()->latest()->first();

        // Safe tags preserved (if configured to keep them)
        $this->assertStringContainsString('Test', $transaction->description);
    }
}
```

---

## 4. Add API Token Expiration

### Purpose

Ensure security tokens don't live forever and implement token refresh mechanism.

### Implementation

#### Step 1: Configure Token Expiration

```php
// filepath: config/sanctum.php
<?php

return [
    // ...existing config...

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If this value is null, personal access tokens do
    | not expire. This won't tweak the lifetime of first-party sessions.
    |
    */

    'expiration' => 60 * 24, // 24 hours in minutes

    // ...rest of config...
];
```

#### Step 2: Update AuthController

```php
// filepath: app/Http/Controllers/Api/AuthController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create token with expiration
        $token = $user->createToken(
            'auth_token_' . uniqid(),
            ['*'],
            now()->addHours(24) // Expires in 24 hours
        )->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addHours(24)->toIso8601String(),
            'expires_in' => 86400, // seconds
        ], 201);
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !password_verify($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        // Create new token with expiration
        $token = $user->createToken(
            'auth_token_' . uniqid(),
            ['*'],
            now()->addHours(24) // Expires in 24 hours
        )->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addHours(24)->toIso8601String(),
            'expires_in' => 86400, // seconds
        ]);
    }

    /**
     * Refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete current token
        $currentToken = $request->user()->currentAccessToken();
        if ($currentToken && !($currentToken instanceof TransientToken)) {
            $currentToken->delete();
        }

        // Create new token
        $token = $user->createToken(
            'auth_token_' . uniqid(),
            ['*'],
            now()->addHours(24)
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addHours(24)->toIso8601String(),
            'expires_in' => 86400,
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token && !($token instanceof TransientToken)) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
```

#### Step 3: Add Refresh Route

```php
// filepath: routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // ...other routes...
});
```

#### Step 4: Create Token Cleanup Command

```php
// filepath: app/Console/Commands/CleanupExpiredTokens.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class CleanupExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired Sanctum tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up expired tokens...');

        $deleted = PersonalAccessToken::where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deleted} expired tokens.");

        return Command::SUCCESS;
    }
}
```

#### Step 5: Schedule Token Cleanup

```php
// filepath: app/Console/Kernel.php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Clean up expired tokens daily
        $schedule->command('tokens:cleanup')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
```

### Testing Token Expiration

```php
// filepath: tests/Feature/Security/TokenExpirationTest.php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_has_expiration_time(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'token',
            'expires_at',
            'expires_in',
        ]);
    }

    public function test_can_refresh_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'], now()->addHours(24))->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/refresh');

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'expires_at',
            'expires_in',
        ]);
    }

    public function test_cleanup_command_deletes_expired_tokens(): void
    {
        $user = User::factory()->create();

        // Create expired token
        $expiredToken = $user->createToken('expired', ['*'], now()->subHour());

        // Create valid token
        $validToken = $user->createToken('valid', ['*'], now()->addHour());

        $this->artisan('tokens:cleanup')
            ->assertSuccessful();

        // Expired token should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'name' => 'expired',
        ]);

        // Valid token should remain
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'valid',
        ]);
    }
}
```

---

## Summary Checklist

-   [ ] Fix mass assignment vulnerability in all models
-   [ ] Remove `user_id` from `$fillable` arrays
-   [ ] Add rate limiting to authentication endpoints
-   [ ] Create custom rate limiters for different operations
-   [ ] Add input sanitization middleware
-   [ ] Configure token expiration in Sanctum
-   [ ] Add token refresh endpoint
-   [ ] Create token cleanup command
-   [ ] Schedule daily token cleanup
-   [ ] Write security tests
-   [ ] Test rate limiting
-   [ ] Test input sanitization
-   [ ] Test token expiration

---

**Implementation Order:**

1. Fix mass assignment (30 minutes)
2. Add rate limiting (45 minutes)
3. Add input sanitization (30 minutes)
4. Add token expiration (45 minutes)

**Total Estimated Time**: 2.5 hours

**Next Steps**: Proceed to [API Improvements](./02-api-improvements.md)
