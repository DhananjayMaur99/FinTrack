<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\TransactionController
 */
final class TransactionControllerTest extends TestCase
{
    use AdditionalAssertions;
    use RefreshDatabase;
    use WithFaker;

    #[Test]
    public function index_returns_authenticated_users_transactions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedTransactions = Transaction::factory()->count(2)->for($user)->create();
        $foreignTransaction = Transaction::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('transactions.index'));

        $response->assertOk();
        $response->assertJsonCount($ownedTransactions->count(), 'data');
        foreach ($ownedTransactions as $transaction) {
            $response->assertJsonFragment(['id' => $transaction->id]);
        }
        $response->assertJsonMissing(['id' => $foreignTransaction->id]);
    }

    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\TransactionController::class,
            'store',
            TransactionStoreRequest::class
        );
    }

    #[Test]
    public function store_creates_transaction_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'amount' => 123.45,
            'description' => 'Coffee beans',
            'date' => now()->toDateString(),
            'category_id' => $category->id,
        ];

        $response = $this->postJson(route('transactions.store'), $payload);

        $response->assertCreated();
        $response->assertJsonFragment([
            'amount' => number_format($payload['amount'], 2, '.', ''),
            'description' => $payload['description'],
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => number_format($payload['amount'], 2, '.', ''),
            'description' => $payload['description'],
            'date' => $payload['date'],
        ]);
    }

    #[Test]
    public function show_returns_transaction_for_owner(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create([
            'amount' => 50,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('transactions.show', $transaction));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $transaction->id,
            'amount' => number_format($transaction->amount, 2, '.', ''),
        ]);
    }

    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\TransactionController::class,
            'update',
            TransactionUpdateRequest::class
        );
    }

    #[Test]
    public function update_modifies_transaction(): void
    {
        $user = User::factory()->create();
        $originalCategory = Category::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($originalCategory)->create([
            'amount' => 20,
            'description' => 'Lunch',
            'date' => now()->subDay()->toDateString(),
        ]);

        $newCategory = Category::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'amount' => 42.88,
            'description' => 'Groceries',
            'date' => now()->toDateString(),
            'category_id' => $newCategory->id,
        ];

        $response = $this->putJson(route('transactions.update', $transaction), $payload);

        $response->assertOk();

        $transaction->refresh();

        $this->assertSame(number_format($payload['amount'], 2, '.', ''), $transaction->amount);
        $this->assertSame($payload['description'], $transaction->description);
        $this->assertSame($payload['category_id'], $transaction->category_id);
        $this->assertSame($payload['date'], $transaction->date->toDateString());
    }

    #[Test]
    public function destroy_soft_deletes_transaction(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('transactions.destroy', $transaction));

        $response->assertNoContent();

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }
}
