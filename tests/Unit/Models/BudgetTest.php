<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_fillable_fields_correctly(): void
    {
        $data = [
            'user_id'     => 1,
            'category_id' => 1,
            'limit'       => 500.00,
            'period'      => 'monthly',
            'start_date'  => '2025-01-01',
            'end_date'    => '2025-01-31',
        ];

        $budget = new Budget($data);

        $this->assertEquals(1, $budget->user_id);
        $this->assertEquals(1, $budget->category_id);
        $this->assertEquals(500.00, $budget->limit);
        $this->assertEquals('monthly', $budget->period);
        // Dates are cast to Carbon, so compare Carbon instances
        $this->assertInstanceOf(Carbon::class, $budget->start_date);
        $this->assertInstanceOf(Carbon::class, $budget->end_date);
        $this->assertEquals('2025-01-01', $budget->start_date->format('Y-m-d'));
        $this->assertEquals('2025-01-31', $budget->end_date->format('Y-m-d'));
    }

    #[Test]
    public function it_casts_limit_to_decimal(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'limit'       => '1000.50',
        ]);

        $this->assertIsFloat($budget->limit);
        $this->assertEquals(1000.50, $budget->limit);
    }

    #[Test]
    public function it_casts_start_date_to_carbon_date(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'start_date'  => '2025-01-01',
        ]);

        $this->assertInstanceOf(Carbon::class, $budget->start_date);
        $this->assertEquals('2025-01-01', $budget->start_date->format('Y-m-d'));
    }

    #[Test]
    public function it_casts_end_date_to_carbon_date(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'end_date'    => '2025-01-31',
        ]);

        $this->assertInstanceOf(Carbon::class, $budget->end_date);
        $this->assertEquals('2025-01-31', $budget->end_date->format('Y-m-d'));
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertNotNull($budget->created_at);
        $this->assertNotNull($budget->updated_at);
        $this->assertInstanceOf(Carbon::class, $budget->created_at);
        $this->assertInstanceOf(Carbon::class, $budget->updated_at);
    }

    #[Test]
    public function it_does_not_use_soft_deletes(): void
    {
        $this->assertNotContains('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Budget::class));

        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
        $budgetId = $budget->id;

        $budget->delete();

        // Should be hard deleted
        $this->assertDatabaseMissing('budgets', ['id' => $budgetId]);
    }

    #[Test]
    public function it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $relationship = $budget->user();

        $this->assertInstanceOf(BelongsTo::class, $relationship);
        $this->assertEquals(User::class, $relationship->getRelated()::class);
        $this->assertEquals($user->id, $budget->user->id);
    }

    #[Test]
    public function it_belongs_to_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $relationship = $budget->category();

        $this->assertInstanceOf(BelongsTo::class, $relationship);
        $this->assertEquals(Category::class, $relationship->getRelated()::class);
        $this->assertEquals($category->id, $budget->category->id);
    }

    #[Test]
    public function period_field_accepts_monthly(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'period'      => 'monthly',
        ]);

        $this->assertEquals('monthly', $budget->period);
        $this->assertDatabaseHas('budgets', [
            'id'     => $budget->id,
            'period' => 'monthly',
        ]);
    }

    #[Test]
    public function period_field_accepts_yearly(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'period'      => 'yearly',
        ]);

        $this->assertEquals('yearly', $budget->period);
        $this->assertDatabaseHas('budgets', [
            'id'     => $budget->id,
            'period' => 'yearly',
        ]);
    }

    #[Test]
    public function end_date_field_is_nullable(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'end_date'    => null,
        ]);

        $this->assertNull($budget->end_date);
        $this->assertDatabaseHas('budgets', [
            'id'       => $budget->id,
            'end_date' => null,
        ]);
    }

    #[Test]
    public function it_allows_mass_assignment_of_fillable_fields(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'limit'       => 750.00,
            'period'      => 'monthly',
            'start_date'  => '2025-03-01',
            'end_date'    => '2025-03-31',
        ]);

        $this->assertDatabaseHas('budgets', [
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'limit'       => 750.00,
            'period'      => 'monthly',
            'start_date'  => '2025-03-01',
            'end_date'    => '2025-03-31',
        ]);
    }

    #[Test]
    public function it_prevents_mass_assignment_of_non_fillable_fields(): void
    {
        $budget = new Budget([
            'id'         => 999,
            'user_id'    => 1,
            'limit'      => 500,
            'period'     => 'monthly',
            'created_at' => '2020-01-01',
        ]);

        $this->assertNull($budget->id);
        $this->assertNull($budget->created_at);
        $this->assertEquals(1, $budget->user_id);
        $this->assertEquals(500, $budget->limit);
    }

    #[Test]
    public function it_eager_loads_user_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $budgetWithUser = Budget::with('user')->find($budget->id);

        $this->assertTrue($budgetWithUser->relationLoaded('user'));
        $this->assertEquals($user->id, $budgetWithUser->user->id);
    }

    #[Test]
    public function it_eager_loads_category_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $budgetWithCategory = Budget::with('category')->find($budget->id);

        $this->assertTrue($budgetWithCategory->relationLoaded('category'));
        $this->assertEquals($category->id, $budgetWithCategory->category->id);
    }

    #[Test]
    public function it_stores_limit_with_two_decimal_precision(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'limit'       => 999.999, // More than 2 decimals
        ]);

        // Should be stored with 2 decimal precision
        $this->assertDatabaseHas('budgets', [
            'id'    => $budget->id,
            'limit' => 1000.00, // Rounded
        ]);
    }

    #[Test]
    public function it_can_calculate_date_range_duration(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'start_date'  => '2025-01-01',
            'end_date'    => '2025-01-31',
        ]);

        $durationInDays = $budget->start_date->diffInDays($budget->end_date);

        $this->assertEquals(30, $durationInDays);
    }

    #[Test]
    public function it_can_check_if_budget_is_active_for_date(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'start_date'  => '2025-01-01',
            'end_date'    => '2025-01-31',
        ]);

        $testDate = Carbon::parse('2025-01-15');

        $isActive = $testDate->between($budget->start_date, $budget->end_date);

        $this->assertTrue($isActive);
    }

    #[Test]
    public function it_can_check_if_date_is_outside_budget_period(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'start_date'  => '2025-01-01',
            'end_date'    => '2025-01-31',
        ]);

        $testDate = Carbon::parse('2025-02-15');

        $isActive = $testDate->between($budget->start_date, $budget->end_date);

        $this->assertFalse($isActive);
    }

    #[Test]
    public function it_updates_timestamp_on_modification(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $originalUpdatedAt = $budget->updated_at;

        sleep(1);

        $budget->limit = 9999.99;
        $budget->save();

        $this->assertNotEquals($originalUpdatedAt, $budget->fresh()->updated_at);
    }

    #[Test]
    public function it_can_load_soft_deleted_category_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $budget = Budget::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        // Soft delete the category
        $category->delete();

        // Should still be able to access soft-deleted category via withTrashed
        $budgetWithCategory = Budget::with(['category' => function ($query) {
            $query->withTrashed();
        }])->find($budget->id);

        $this->assertNotNull($budgetWithCategory->category);
        $this->assertEquals($category->id, $budgetWithCategory->category->id);
        $this->assertNotNull($budgetWithCategory->category->deleted_at);
    }
}
