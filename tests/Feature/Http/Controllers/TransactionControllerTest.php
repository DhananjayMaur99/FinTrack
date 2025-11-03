<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Foreign;
use App\Models\Transaction;
use App\Models\UpdateTransactionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\TransactionController
 */
final class TransactionControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $transactions = Transaction::factory()->count(3)->create();

        $response = $this->get(route('transactions.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\TransactionController::class,
            'store',
            \App\Http\Requests\TransactionStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $user = Foreign::factory()->create();
        $category = Foreign::factory()->create();
        $amount = fake()->randomFloat(/** decimal_attributes **/);
        $description = fake()->text();
        $date = Carbon::parse(fake()->date());

        $response = $this->post(route('transactions.store'), [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'description' => $description,
            'date' => $date->toDateString(),
        ]);

        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->where('amount', $amount)
            ->where('description', $description)
            ->where('date', $date)
            ->get();
        $this->assertCount(1, $transactions);
        $transaction = $transactions->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $transaction = Transaction::factory()->create();

        $response = $this->get(route('transactions.show', $transaction));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\TransactionController::class,
            'update',
            \App\Http\Requests\TransactionUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $transaction = Transaction::factory()->create();
        $user = Foreign::factory()->create();
        $category = Foreign::factory()->create();
        $amount = fake()->randomFloat(/** decimal_attributes **/);
        $description = fake()->text();
        $date = Carbon::parse(fake()->date());

        $response = $this->put(route('transactions.update', $transaction), [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'description' => $description,
            'date' => $date->toDateString(),
        ]);

        $transaction->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($user->id, $transaction->user_id);
        $this->assertEquals($category->id, $transaction->category_id);
        $this->assertEquals($amount, $transaction->amount);
        $this->assertEquals($description, $transaction->description);
        $this->assertEquals($date, $transaction->date);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $transaction = Transaction::factory()->create();

        $response = $this->delete(route('transactions.destroy', $transaction));

        $response->assertNoContent();

        $this->assertSoftDeleted($transaction);
    }


    #[Test]
    public function requests_behaves_as_expected(): void
    {
        $response = $this->get(route('transactions.requests'));

        $transaction->refresh();

        $response->assertSessionHas('StoreTransactionRequest', $StoreTransactionRequest);
    }
}
