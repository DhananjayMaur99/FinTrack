<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_fillable_fields_correctly(): void
    {
        $data = [
            'user_id'     => 1,
            'category_id' => 1,
            'amount'      => 100.50,
            'date'        => '2025-01-15',
            'description' => 'Grocery shopping',
        ];

        $transaction = new Transaction($data);

        $this->assertEquals(1, $transaction->user_id);
        $this->assertEquals(1, $transaction->category_id);
        $this->assertEquals(100.50, $transaction->amount);
        // Date is cast to Carbon, so compare Carbon instance
        $this->assertInstanceOf(Carbon::class, $transaction->date);
        $this->assertEquals('2025-01-15', $transaction->date->format('Y-m-d'));
        $this->assertEquals('Grocery shopping', $transaction->description);
    }

    #[Test]
    public function it_casts_amount_to_decimal(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'amount'      => '100.50',
        ]);

        // Amount is cast to 'decimal:2' which returns a string with 2 decimal places
        $this->assertIsString($transaction->amount);
        $this->assertEquals('100.50', $transaction->amount);
    }
    #[Test]
    public function it_casts_date_to_carbon_date(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'date'        => '2025-01-15',
        ]);

        $this->assertInstanceOf(Carbon::class, $transaction->date);
        $this->assertEquals('2025-01-15', $transaction->date->format('Y-m-d'));
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertNotNull($transaction->created_at);
        $this->assertNotNull($transaction->updated_at);
        $this->assertInstanceOf(Carbon::class, $transaction->created_at);
        $this->assertInstanceOf(Carbon::class, $transaction->updated_at);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses(Transaction::class));

        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
        $transactionId = $transaction->id;

        $transaction->delete();

        $this->assertSoftDeleted('transactions', ['id' => $transactionId]);
        $this->assertNotNull($transaction->fresh()->deleted_at);
    }

    #[Test]
    public function it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $relationship = $transaction->user();

        $this->assertInstanceOf(BelongsTo::class, $relationship);
        $this->assertEquals(User::class, $relationship->getRelated()::class);
        $this->assertEquals($user->id, $transaction->user->id);
    }

    #[Test]
    public function it_belongs_to_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $relationship = $transaction->category();

        $this->assertInstanceOf(BelongsTo::class, $relationship);
        $this->assertEquals(Category::class, $relationship->getRelated()::class);
        $this->assertEquals($category->id, $transaction->category->id);
    }

    #[Test]
    public function description_field_is_nullable(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'description' => null,
        ]);

        $this->assertNull($transaction->description);
        $this->assertDatabaseHas('transactions', [
            'id'          => $transaction->id,
            'description' => null,
        ]);
    }

    #[Test]
    public function it_can_retrieve_only_non_deleted_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(3)->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $deletedTransaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
        $deletedTransaction->delete();

        $activeTransactions = Transaction::all();

        $this->assertEquals(3, $activeTransactions->count());
        $this->assertFalse($activeTransactions->contains('id', $deletedTransaction->id));
    }

    #[Test]
    public function it_can_retrieve_with_trashed_transactions(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(3)->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $deletedTransaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
        $deletedTransaction->delete();

        $allTransactions = Transaction::withTrashed()->get();

        $this->assertEquals(4, $allTransactions->count());
        $this->assertTrue($allTransactions->contains('id', $deletedTransaction->id));
    }

    #[Test]
    public function it_can_restore_soft_deleted_transaction(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
        $transactionId = $transaction->id;

        $transaction->delete();
        $this->assertSoftDeleted('transactions', ['id' => $transactionId]);

        $transaction->restore();
        $this->assertDatabaseHas('transactions', [
            'id'         => $transactionId,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_can_force_delete_transaction_permanently(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
        $transactionId = $transaction->id;

        $transaction->forceDelete();

        $this->assertDatabaseMissing('transactions', ['id' => $transactionId]);
        $this->assertNull(Transaction::withTrashed()->find($transactionId));
    }

    #[Test]
    public function it_allows_mass_assignment_of_fillable_fields(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = Transaction::create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'amount'      => 250.75,
            'date'        => '2025-02-01',
            'description' => 'Test purchase',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'amount'      => 250.75,
            'date'        => '2025-02-01',
            'description' => 'Test purchase',
        ]);
    }

    #[Test]
    public function it_prevents_mass_assignment_of_non_fillable_fields(): void
    {
        $transaction = new Transaction([
            'id'         => 999,
            'user_id'    => 1,
            'amount'     => 100.00,
            'created_at' => '2020-01-01',
        ]);

        $this->assertNull($transaction->id);
        $this->assertNull($transaction->created_at);
        $this->assertEquals(1, $transaction->user_id);
        $this->assertEquals(100.00, $transaction->amount);
    }

    #[Test]
    public function it_eager_loads_user_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $transactionWithUser = Transaction::with('user')->find($transaction->id);

        $this->assertTrue($transactionWithUser->relationLoaded('user'));
        $this->assertEquals($user->id, $transactionWithUser->user->id);
    }

    #[Test]
    public function it_eager_loads_category_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $transactionWithCategory = Transaction::with('category')->find($transaction->id);

        $this->assertTrue($transactionWithCategory->relationLoaded('category'));
        $this->assertEquals($category->id, $transactionWithCategory->category->id);
    }

    #[Test]
    public function it_can_load_soft_deleted_category_relationship(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        // Soft delete the category
        $category->delete();

        // Should still be able to access the soft-deleted category via withTrashed
        $transactionWithCategory = Transaction::with(['category' => function ($query) {
            $query->withTrashed();
        }])->find($transaction->id);

        $this->assertNotNull($transactionWithCategory->category);
        $this->assertEquals($category->id, $transactionWithCategory->category->id);
        $this->assertNotNull($transactionWithCategory->category->deleted_at);
    }

    #[Test]
    public function it_stores_amount_with_two_decimal_precision(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);

        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'amount'      => 123.456, // More than 2 decimals
        ]);

        // Should be stored with 2 decimal precision in DB (database constraint)
        $this->assertDatabaseHas('transactions', [
            'id'     => $transaction->id,
            'amount' => 123.46, // Rounded to 2 decimals
        ]);
    }

    #[Test]
    public function it_updates_timestamp_on_modification(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $originalUpdatedAt = $transaction->updated_at;

        sleep(1);

        $transaction->amount = 999.99;
        $transaction->save();

        $this->assertNotEquals($originalUpdatedAt, $transaction->fresh()->updated_at);
    }
}
