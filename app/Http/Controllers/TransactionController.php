<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Http\Resources\TransactionCollection;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of the user's transactions.
     * SECURE: Now only shows transactions for the logged-in user.
     */
    public function index(Request $request): TransactionCollection
    {
        // We use the relationship to get only the user's transactions
        $transactions = $request->user()->transactions()->latest()->paginate();

        return new TransactionCollection($transactions);
    }

    /**
     * Store a new transaction for the logged-in user.
     * SECURE: Automatically assigns the transaction to the user.
     */
    public function store(TransactionStoreRequest $request): TransactionResource
    {
        // Authorize that the user is allowed to create (e.g., is not banned)
        $this->authorize('create', Transaction::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Create the transaction using the relationship to auto-set user_id
        $transaction = $user->transactions()->create($request->validated());

        return new TransactionResource($transaction);
    }

    /**
     * Display a specific transaction.
     * SECURE: Checks for ownership via the TransactionPolicy.
     */
    public function show(Transaction $transaction): TransactionResource
    {
        // Check if the user is allowed to 'view' this specific transaction
        $this->authorize('view', $transaction);

        return new TransactionResource($transaction);
    }

    /**
     * Update a specific transaction.
     * SECURE: Checks for ownership via the TransactionPolicy.
     */
    public function update(TransactionUpdateRequest $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('update', $transaction);

        $transaction->update($request->validated());

        return new TransactionResource($transaction->refresh());
    }

    /**
     * Delete a specific transaction.
     * SECURE: Checks for ownership via the TransactionPolicy.
     */
    public function destroy(Transaction $transaction): Response
    {
        // Check if the user is allowed to 'delete' this specific transaction
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->noContent();
    }
}
