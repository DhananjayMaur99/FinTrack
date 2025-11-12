<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_fillable_fields_correctly(): void
    {
        $data = [
            'user_id' => 1,
            'name'    => 'Food',
            'icon'    => '🍕',
        ];

        $category = new Category($data);

        $this->assertEquals(1, $category->user_id);
        $this->assertEquals('Food', $category->name);
        $this->assertEquals('🍕', $category->icon);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $this->assertNotNull($category->created_at);
        $this->assertNotNull($category->updated_at);
        $this->assertInstanceOf(Carbon::class, $category->created_at);
        $this->assertInstanceOf(Carbon::class, $category->updated_at);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses(Category::class));

        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $categoryId = $category->id;

        $category->delete();

        $this->assertSoftDeleted('categories', ['id' => $categoryId]);
        $this->assertNotNull($category->fresh()->deleted_at);
    }

    #[Test]
    public function it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $relationship = $category->user();

        $this->assertInstanceOf(BelongsTo::class, $relationship);
        $this->assertEquals(User::class, $relationship->getRelated()::class);
        $this->assertEquals($user->id, $category->user->id);
    }

    #[Test]
    public function it_has_many_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $relationship = $category->transactions();

        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertEquals(Transaction::class, $relationship->getRelated()::class);
    }

    #[Test]
    public function it_can_create_transactions_through_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = $category->transactions()->create([
            'user_id'     => $user->id,
            'amount'      => 50.00,
            'date'        => '2025-01-01',
            'description' => 'Test transaction',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id'          => $transaction->id,
            'category_id' => $category->id,
        ]);
        $this->assertEquals(1, $category->transactions()->count());
    }

    #[Test]
    public function it_soft_deletes_without_cascading_to_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $category->delete();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('transactions', [
            'id'          => $transaction->id,
            'category_id' => $category->id,
        ]);
    }

    #[Test]
    public function it_can_retrieve_only_non_deleted_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->count(3)->create(['user_id' => $user->id]);
        $deletedCategory = Category::factory()->create(['user_id' => $user->id]);
        $deletedCategory->delete();

        $activeCategories = Category::all();

        $this->assertEquals(3, $activeCategories->count());
        $this->assertFalse($activeCategories->contains('id', $deletedCategory->id));
    }

    #[Test]
    public function it_can_retrieve_with_trashed_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->count(3)->create(['user_id' => $user->id]);
        $deletedCategory = Category::factory()->create(['user_id' => $user->id]);
        $deletedCategory->delete();

        $allCategories = Category::withTrashed()->get();

        $this->assertEquals(4, $allCategories->count());
        $this->assertTrue($allCategories->contains('id', $deletedCategory->id));
    }

    #[Test]
    public function it_can_restore_soft_deleted_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $categoryId = $category->id;

        $category->delete();
        $this->assertSoftDeleted('categories', ['id' => $categoryId]);

        $category->restore();
        $this->assertDatabaseHas('categories', [
            'id'         => $categoryId,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_can_force_delete_category_permanently(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $categoryId = $category->id;

        $category->forceDelete();

        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
        $this->assertNull(Category::withTrashed()->find($categoryId));
    }

    #[Test]
    public function icon_field_is_nullable(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'user_id' => $user->id,
            'icon'    => null,
        ]);

        $this->assertNull($category->icon);
        $this->assertDatabaseHas('categories', [
            'id'   => $category->id,
            'icon' => null,
        ]);
    }

    #[Test]
    public function it_allows_mass_assignment_of_fillable_fields(): void
    {
        $user = User::factory()->create();

        $category = Category::create([
            'user_id' => $user->id,
            'name'    => 'Transport',
            'icon'    => '🚗',
        ]);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name'    => 'Transport',
            'icon'    => '🚗',
        ]);
    }

    #[Test]
    public function it_prevents_mass_assignment_of_non_fillable_fields(): void
    {
        $category = new Category([
            'id'         => 999,
            'user_id'    => 1,
            'name'       => 'Food',
            'icon'       => '🍕',
            'created_at' => '2020-01-01',
        ]);

        $this->assertNull($category->id);
        $this->assertNull($category->created_at);
        $this->assertEquals(1, $category->user_id);
        $this->assertEquals('Food', $category->name);
    }

    #[Test]
    public function it_eager_loads_user_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $categoryWithUser = Category::with('user')->find($category->id);

        $this->assertTrue($categoryWithUser->relationLoaded('user'));
        $this->assertEquals($user->id, $categoryWithUser->user->id);
    }

    #[Test]
    public function it_eager_loads_transactions_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        Transaction::factory()->count(3)->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $categoryWithTransactions = Category::with('transactions')->find($category->id);

        $this->assertTrue($categoryWithTransactions->relationLoaded('transactions'));
        $this->assertEquals(3, $categoryWithTransactions->transactions->count());
    }

    #[Test]
    public function it_updates_timestamp_on_modification(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $originalUpdatedAt = $category->updated_at;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $category->name = 'Updated Name';
        $category->save();

        $this->assertNotEquals($originalUpdatedAt, $category->fresh()->updated_at);
    }
}
