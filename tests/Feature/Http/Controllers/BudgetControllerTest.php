<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Budget;
use App\Models\Foreign;
use App\Models\UpdateBudgetRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\BudgetController
 */
final class BudgetControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $budgets = Budget::factory()->count(3)->create();

        $response = $this->get(route('budgets.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\BudgetController::class,
            'store',
            \App\Http\Requests\BudgetStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $user = Foreign::factory()->create();
        $category = Foreign::factory()->create();
        $limit = fake()->randomFloat(/** decimal_attributes **/);
        $period = fake()->randomElement(/** enum_attributes **/);
        $start_date = Carbon::parse(fake()->date());
        $end_date = Carbon::parse(fake()->date());

        $response = $this->post(route('budgets.store'), [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'limit' => $limit,
            'period' => $period,
            'start_date' => $start_date->toDateString(),
            'end_date' => $end_date->toDateString(),
        ]);

        $budgets = Budget::query()
            ->where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->where('limit', $limit)
            ->where('period', $period)
            ->where('start_date', $start_date)
            ->where('end_date', $end_date)
            ->get();
        $this->assertCount(1, $budgets);
        $budget = $budgets->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $budget = Budget::factory()->create();

        $response = $this->get(route('budgets.show', $budget));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\BudgetController::class,
            'update',
            \App\Http\Requests\BudgetUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $budget = Budget::factory()->create();
        $user = Foreign::factory()->create();
        $category = Foreign::factory()->create();
        $limit = fake()->randomFloat(/** decimal_attributes **/);
        $period = fake()->randomElement(/** enum_attributes **/);
        $start_date = Carbon::parse(fake()->date());
        $end_date = Carbon::parse(fake()->date());

        $response = $this->put(route('budgets.update', $budget), [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'limit' => $limit,
            'period' => $period,
            'start_date' => $start_date->toDateString(),
            'end_date' => $end_date->toDateString(),
        ]);

        $budget->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($user->id, $budget->user_id);
        $this->assertEquals($category->id, $budget->category_id);
        $this->assertEquals($limit, $budget->limit);
        $this->assertEquals($period, $budget->period);
        $this->assertEquals($start_date, $budget->start_date);
        $this->assertEquals($end_date, $budget->end_date);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $budget = Budget::factory()->create();

        $response = $this->delete(route('budgets.destroy', $budget));

        $response->assertNoContent();

        $this->assertModelMissing($budget);
    }


    #[Test]
    public function requests_behaves_as_expected(): void
    {
        $response = $this->get(route('budgets.requests'));

        $budget->refresh();

        $response->assertSessionHas('StoreBudgetRequest', $StoreBudgetRequest);
    }
}
