<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_fillable_fields_correctly(): void
    {
        $data = [
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => 'plain_password', // Will be hashed automatically
            'timezone' => 'America/New_York',
        ];

        $user = new User($data);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        // Password is automatically hashed, so we check if it's hashed
        $this->assertNotEquals('plain_password', $user->password);
        $this->assertEquals('America/New_York', $user->timezone);
    }

    #[Test]
    public function it_hides_password_in_array_representation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret'),
        ]);

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
    }

    #[Test]
    public function it_casts_password_to_hashed(): void
    {
        $user = new User();
        $user->password = 'plain_password';

        // Password should be automatically hashed
        $this->assertNotEquals('plain_password', $user->password);
        $this->assertTrue(Hash::check('plain_password', $user->password));
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
        $this->assertInstanceOf(Carbon::class, $user->created_at);
        $this->assertInstanceOf(Carbon::class, $user->updated_at);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses(User::class));

        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        // Should be soft deleted
        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->assertNotNull($user->fresh()->deleted_at);
    }

    #[Test]
    public function it_has_api_tokens_trait(): void
    {
        $this->assertContains(HasApiTokens::class, class_uses(User::class));
    }

    #[Test]
    public function it_has_many_transactions_relationship(): void
    {
        $user = User::factory()->create();

        $relationship = $user->transactions();

        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertEquals(Transaction::class, $relationship->getRelated()::class);
    }

    #[Test]
    public function it_can_create_transactions_through_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = $user->transactions()->create([
            'category_id' => $category->id,
            'amount'      => 100.50,
            'date'        => '2025-01-01',
            'description' => 'Test transaction',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id'      => $transaction->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(1, $user->transactions()->count());
    }

    #[Test]
    public function it_has_many_categories_relationship(): void
    {
        $user = User::factory()->create();

        $relationship = $user->categories();

        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertEquals(Category::class, $relationship->getRelated()::class);
    }

    #[Test]
    public function it_can_create_categories_through_relationship(): void
    {
        $user = User::factory()->create();

        $category = $user->categories()->create([
            'name' => 'Food',
            'icon' => '🍕',
        ]);

        $this->assertDatabaseHas('categories', [
            'id'      => $category->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(1, $user->categories()->count());
    }

    #[Test]
    public function it_has_many_budgets_relationship(): void
    {
        $user = User::factory()->create();

        $relationship = $user->budgets();

        $this->assertInstanceOf(HasMany::class, $relationship);
        $this->assertEquals(Budget::class, $relationship->getRelated()::class);
    }

    #[Test]
    public function it_can_create_budgets_through_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $budget = $user->budgets()->create([
            'category_id' => $category->id,
            'limit'       => 500,
            'period'      => 'monthly',
            'start_date'  => '2025-01-01',
            'end_date'    => '2025-01-31',
        ]);

        $this->assertDatabaseHas('budgets', [
            'id'      => $budget->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(1, $user->budgets()->count());
    }

    #[Test]
    public function it_deletes_user_without_cascading_hard_delete_to_relationships(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        $budget = Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);

        $user->delete(); // Soft delete

        // User should be soft deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // Relationships should still exist (not cascaded)
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    #[Test]
    public function it_can_retrieve_only_non_deleted_users(): void
    {
        User::factory()->count(3)->create();
        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $activeUsers = User::all();

        $this->assertEquals(3, $activeUsers->count());
        $this->assertFalse($activeUsers->contains('id', $deletedUser->id));
    }

    #[Test]
    public function it_can_retrieve_with_trashed_users(): void
    {
        User::factory()->count(3)->create();
        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $allUsers = User::withTrashed()->get();

        $this->assertEquals(4, $allUsers->count());
        $this->assertTrue($allUsers->contains('id', $deletedUser->id));
    }

    #[Test]
    public function it_can_restore_soft_deleted_user(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $userId]);

        $user->restore();
        $this->assertDatabaseHas('users', [
            'id'         => $userId,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_can_force_delete_user_permanently(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertNull(User::withTrashed()->find($userId));
    }

    #[Test]
    public function timezone_field_is_nullable(): void
    {
        $user = User::factory()->create([
            'timezone' => null,
        ]);

        $this->assertNull($user->timezone);
        $this->assertDatabaseHas('users', [
            'id'       => $user->id,
            'timezone' => null,
        ]);
    }

    #[Test]
    public function it_allows_mass_assignment_of_fillable_fields(): void
    {
        $user = User::create([
            'name'     => 'Jane Doe',
            'email'    => 'jane@example.com',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);

        $this->assertDatabaseHas('users', [
            'name'     => 'Jane Doe',
            'email'    => 'jane@example.com',
            'timezone' => 'UTC',
        ]);
    }

    #[Test]
    public function it_prevents_mass_assignment_of_non_fillable_fields(): void
    {
        // Attempt to mass assign 'id' and 'remember_token'
        $user = new User([
            'id'             => 999,
            'name'           => 'Test User',
            'email'          => 'test@example.com',
            'password'       => 'password',
            'remember_token' => 'should_not_work',
        ]);

        // ID should not be set via mass assignment
        $this->assertNull($user->id);
        // Remember token should not be set via mass assignment
        $this->assertNull($user->remember_token);
        // Fillable fields should work
        $this->assertEquals('Test User', $user->name);
    }

    #[Test]
    public function it_eager_loads_relationships_efficiently(): void
    {
        $user = User::factory()->create();
        Category::factory()->count(3)->create(['user_id' => $user->id]);

        $category = $user->categories()->first();
        Transaction::factory()->count(5)->create(['user_id' => $user->id, 'category_id' => $category->id]);
        Budget::factory()->count(2)->create(['user_id' => $user->id, 'category_id' => $category->id]);

        // Eager load all relationships
        $userWithRelations = User::with(['transactions', 'categories', 'budgets'])->find($user->id);

        $this->assertInstanceOf(User::class, $userWithRelations);
        $this->assertTrue($userWithRelations->relationLoaded('transactions'));
        $this->assertTrue($userWithRelations->relationLoaded('categories'));
        $this->assertTrue($userWithRelations->relationLoaded('budgets'));
        $this->assertEquals(5, $userWithRelations->transactions->count());
        $this->assertEquals(3, $userWithRelations->categories->count());
        $this->assertEquals(2, $userWithRelations->budgets->count());
    }
}
