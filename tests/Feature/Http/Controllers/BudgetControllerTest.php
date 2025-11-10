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
        foreach ($ownedBudgets as $budget) {
            $response->assertJsonFragment(['id' => $budget->id]);
        }
        $response->assertJsonMissing(['id' => $foreignBudget->id]);
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

        $newCategory = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'limit' => 750,
            'period' => 'yearly',
            'end_date' => now()->addYear()->toDateString(),
            'category_id' => $newCategory->id,
        ];

        $response = $this->putJson(route('budgets.update', $budget), $payload);

        $response->assertOk();

        $budget->refresh();

        $this->assertEquals(750.0, $budget->limit);
        $this->assertSame('yearly', $budget->period);
        $this->assertSame($payload['category_id'], $budget->category_id);
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
}
