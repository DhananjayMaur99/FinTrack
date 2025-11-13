<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Requests\BudgetStoreRequest;
use App\Http\Requests\BudgetUpdateRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\BudgetController
 */
final class BudgetControllerTest extends TestCase
{
    use AdditionalAssertions;
    use RefreshDatabase;
    use WithFaker;

    #[Test]
    public function index_returns_only_authenticated_users_budgets(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedBudgets = Budget::factory()->count(2)->for($user)->create();
        $foreignBudget = Budget::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.index'));

        $response->assertOk();
        $response->assertJsonCount($ownedBudgets->count(), 'data');

        // Check budget IDs are present in data array
        $budgetIds = collect($response->json('data'))->pluck('id')->toArray();
        foreach ($ownedBudgets as $budget) {
            $this->assertContains($budget->id, $budgetIds);
        }
        $this->assertNotContains($foreignBudget->id, $budgetIds);
    }

    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\BudgetController::class,
            'store',
            BudgetStoreRequest::class
        );
    }

    #[Test]
    public function store_creates_budget_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'limit' => 1500.50,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'category_id' => $category->id,
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertCreated();
        // Check a few key attributes and ensure the new resource shape is present
        $response->assertJsonFragment([
            'period' => $payload['period'],
        ]);
        // The API now returns a nested `category` object; assert its id via json path
        $response->assertJsonPath('data.category.id', $category->id);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'user_id',
                'category',
                'limit',
                'period',
                'range' => [
                    'start',
                    'end',
                ],
                'stats',
            ],
        ]);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'limit' => number_format($payload['limit'], 2, '.', ''),
            'period' => $payload['period'],
        ]);
    }

    #[Test]
    public function show_returns_budget_with_progress_stats(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create([
            'limit' => 200,
            'start_date' => now()->subWeek()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.show', $budget));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'stats' => [
                    'spent',
                    'remaining',
                    'progress_percent',
                    'over',
                ],
            ],
        ]);
    }

    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\BudgetController::class,
            'update',
            BudgetUpdateRequest::class
        );
    }

    #[Test]
    public function update_modifies_budget(): void
    {
        $user = User::factory()->create();
        $originalCategory = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($originalCategory)->create([
            'limit' => 500,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        // create another category to ensure payload might try to change it, but updates should not permit category changes
        $newCategory = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'limit' => 750,
            'period' => 'yearly',
            'end_date' => now()->addYear()->toDateString(),
            // category_id intentionally omitted: category changes are not allowed on update
        ];

        $response = $this->putJson(route('budgets.update', $budget), $payload);

        $response->assertOk();

        $budget->refresh();

        $this->assertEquals(750.0, $budget->limit);
        $this->assertSame('yearly', $budget->period);
        // Category should remain unchanged
        $this->assertSame($originalCategory->id, $budget->category_id);
        $this->assertSame($payload['end_date'], $budget->end_date?->toDateString());
    }

    #[Test]
    public function destroy_deletes_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('budgets.destroy', $budget));

        $response->assertNoContent();
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    // ========================================
    // ADDITIONAL INDEX TESTS
    // ========================================

    #[Test]
    public function index_returns_empty_array_when_user_has_no_budgets(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.index'));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function index_without_authentication_returns_401(): void
    {
        $response = $this->getJson(route('budgets.index'));

        $response->assertStatus(401);
    }

    #[Test]
    public function index_includes_budget_progress_stats(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create([
            'limit' => 1000.00,
        ]);

        // Create some transactions to test stats calculation
        \App\Models\Transaction::factory()->for($user)->for($category)->create([
            'amount' => 250.00,
            'date' => $budget->start_date,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'stats' => [
                            'spent',
                            'remaining',
                            'progress_percent',
                            'over',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function index_includes_category_information(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Food']);
        Budget::factory()->for($user)->for($category)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.index'));

        $response->assertStatus(200)
            ->assertJsonPath('data.0.category.name', 'Food');
    }

    // ========================================
    // ADDITIONAL STORE TESTS
    // ========================================

    #[Test]
    public function store_fails_with_missing_category_id_and_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'limit' => 1000.00,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function store_fails_with_invalid_category_id_and_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'category_id' => 99999,
            'limit' => 1000.00,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function store_fails_with_another_users_category_and_returns_422(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $payload = [
            'category_id' => $otherCategory->id,
            'limit' => 1000.00,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function store_fails_with_missing_limit_and_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'category_id' => $category->id,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    #[Test]
    public function store_fails_with_negative_limit_and_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'category_id' => $category->id,
            'limit' => -100.00,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    #[Test]
    public function store_fails_with_missing_period_and_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'category_id' => $category->id,
            'limit' => 1000.00,
            'start_date' => now()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    #[Test]
    public function store_fails_with_invalid_period_and_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'category_id' => $category->id,
            'limit' => 1000.00,
            'period' => 'daily', // Invalid: only weekly, monthly, yearly allowed
            'start_date' => now()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    #[Test]
    public function store_fails_with_missing_start_date_and_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'category_id' => $category->id,
            'limit' => 1000.00,
            'period' => 'monthly',
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    #[Test]
    public function store_with_end_date_before_start_date_fails_with_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'category_id' => $category->id,
            'limit' => 1000.00,
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function store_without_end_date_auto_calculates_based_on_period(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $startDate = '2025-01-01';

        $payload = [
            'category_id' => $category->id,
            'limit' => 1000.00,
            'period' => 'monthly',
            'start_date' => $startDate,
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(201);

        // For monthly, end_date should be start + 1 month - 1 day
        $expectedEndDate = \Carbon\Carbon::parse($startDate)->addMonth()->subDay()->toDateString();
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'start_date' => $startDate,
            'end_date' => $expectedEndDate,
        ]);
    }

    // #[Test]
    // public function store_with_weekly_period_fails_with_422(): void
    // {

    //     $this->markTestSkipped('Validation allows weekly but DB enum does not - application bug');

    //     $category = Category::factory()->create(['user_id' => $this->user->id]);

    //     $payload = [
    //         'category_id' => $category->id,
    //         'limit'       => 500,
    //         'period'      => 'weekly', // Not in DB enum
    //         'start_date'  => '2025-01-01',
    //     ];

    //     $response = $this->postJson(route('budgets.store'), $payload);

    //     $response->assertStatus(422)
    //         ->assertJsonValidationErrors(['period']);
    // }
    #[Test]
    public function store_with_yearly_period_calculates_correct_end_date(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $startDate = '2025-01-01';

        $payload = [
            'category_id' => $category->id,
            'limit' => 12000.00,
            'period' => 'yearly',
            'start_date' => $startDate,
        ];

        $response = $this->postJson(route('budgets.store'), $payload);

        $response->assertStatus(201);

        $expectedEndDate = \Carbon\Carbon::parse($startDate)->addYear()->subDay()->toDateString();
        $this->assertDatabaseHas('budgets', [
            'period' => 'yearly',
            'end_date' => $expectedEndDate,
        ]);
    }

    #[Test]
    public function store_without_authentication_returns_401(): void
    {
        $response = $this->postJson(route('budgets.store'), []);

        $response->assertStatus(401);
    }



    #[Test]
    public function show_for_non_owner_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(route('budgets.show', $budget));

        $response->assertStatus(403);
    }

    #[Test]
    public function show_for_nonexistent_budget_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.show', 99999));

        $response->assertStatus(404);
    }

    #[Test]
    public function show_without_authentication_returns_401(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $response = $this->getJson(route('budgets.show', $budget));

        $response->assertStatus(401);
    }

    #[Test]
    public function show_calculates_progress_with_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create([
            'limit' => 1000.00,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        // Create transactions within budget period
        \App\Models\Transaction::factory()->for($user)->for($category)->create([
            'amount' => 300.00,
            'date' => now()->toDateString(),
        ]);
        \App\Models\Transaction::factory()->for($user)->for($category)->create([
            'amount' => 200.00,
            'date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.show', $budget));

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.spent', 500)
            ->assertJsonPath('data.stats.remaining', 500)
            ->assertJsonPath('data.stats.progress_percent', 50)
            ->assertJsonPath('data.stats.over', false);
    }

    #[Test]
    public function show_indicates_budget_is_over_when_exceeded(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create([
            'limit' => 500.00,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]);

        // Create transactions exceeding budget
        \App\Models\Transaction::factory()->for($user)->for($category)->create([
            'amount' => 600.00,
            'date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('budgets.show', $budget));

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.spent', 600)
            ->assertJsonPath('data.stats.over', true);
    }

    // ========================================
    // ADDITIONAL UPDATE TESTS
    // ========================================

    #[Test]
    public function update_with_only_limit_updates_limit_only(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create([
            'limit' => 1000.00,
            'period' => 'monthly',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(route('budgets.update', $budget), [
            'limit' => 1500.00,
        ]);

        $response->assertStatus(200);

        $budget->refresh();
        $this->assertEquals(1500.00, $budget->limit);
        $this->assertEquals('monthly', $budget->period);
    }

    #[Test]
    public function update_with_only_period_updates_period_only(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create([
            'limit' => 1000.00,
            'period' => 'monthly',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(route('budgets.update', $budget), [
            'period' => 'yearly',
        ]);

        $response->assertStatus(200);

        $budget->refresh();
        $this->assertEquals(1000.00, $budget->limit);
        $this->assertEquals('yearly', $budget->period);
    }

    #[Test]
    public function update_with_category_id_in_payload_returns_422_prohibited(): void
    {
        // Note: Category changes are explicitly prohibited in BudgetUpdateRequest
        $user = User::factory()->create();
        $originalCategory = Category::factory()->for($user)->create();
        $newCategory = Category::factory()->for($user)->create();

        $budget = Budget::factory()->for($user)->for($originalCategory)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(route('budgets.update', $budget), [
            'category_id' => $newCategory->id,
            'limit' => 2000.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);

        $budget->refresh();
        // Category should remain unchanged
        $this->assertEquals($originalCategory->id, $budget->category_id);
    }
    #[Test]
    public function update_by_non_owner_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();

        Sanctum::actingAs($otherUser);

        $response = $this->putJson(route('budgets.update', $budget), [
            'limit' => 5000.00,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function update_without_authentication_returns_401(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $response = $this->putJson(route('budgets.update', $budget), [
            'limit' => 2000.00,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function update_with_negative_limit_returns_422(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(route('budgets.update', $budget), [
            'limit' => -500.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    #[Test]
    public function update_with_invalid_period_returns_422(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(route('budgets.update', $budget), [
            'period' => 'daily',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    #[Test]
    public function update_end_date_requires_start_date_context_for_validation(): void
    {
        // Note: Update validates end_date with start_date, needs start_date in payload
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        // Provide both start_date and end_date
        $response = $this->putJson(route('budgets.update', $budget), [
            'start_date' => now()->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function update_with_empty_payload_returns_422(): void
    {
        // Empty payload should fail validation - at least one field required
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(route('budgets.update', $budget), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payload']);
    }

    // ========================================
    // ADDITIONAL DESTROY TESTS
    // ========================================

    #[Test]
    public function destroy_by_non_owner_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $budget = Budget::factory()->for($owner)->create();

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson(route('budgets.destroy', $budget));

        $response->assertStatus(403);

        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    #[Test]
    public function destroy_without_authentication_returns_401(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $response = $this->deleteJson(route('budgets.destroy', $budget));

        $response->assertStatus(401);
    }

    #[Test]
    public function destroy_nonexistent_budget_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('budgets.destroy', 99999));

        $response->assertStatus(404);
    }

    #[Test]
    public function destroy_budget_with_transactions_succeeds(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create();

        // Create transactions within the budget period
        \App\Models\Transaction::factory()->count(3)->for($user)->for($category)->create([
            'date' => $budget->start_date,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('budgets.destroy', $budget));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);

        // Transactions should still exist (budget deletion doesn't cascade)
        $this->assertEquals(3, \App\Models\Transaction::where('category_id', $category->id)->count());
    }
}
