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
     */
    public function index(Request $request): TransactionCollection
    {
        // We use the relationship to get only the user's transactions
        $transactions = $request->user()->transactions()->latest()->paginate();

        return new TransactionCollection($transactions);
    }

    /**
     * Store a new transaction for the logged-in user.
     * Automatically assigns the transaction to the user.
     */
    public function store(TransactionStoreRequest $request): TransactionResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Create the transaction using the relationship to auto-set user_id
        $payload = $request->validated();
        $transaction = $user->transactions()->create($payload);

        return new TransactionResource($transaction);
    }

    /**
     * Display a specific transaction.
     * SECURE: Checks for ownership via the AuthorizeUser middleware.
     */
    public function show(Transaction $transaction): TransactionResource
    {
        return new TransactionResource($transaction);
    }

    /**
     * Update a specific transaction.
     * SECURE: Checks for ownership via the AuthorizeUser middleware.
     */
    public function update(TransactionUpdateRequest $request, Transaction $transaction): TransactionResource
    {
        $payload = $request->validated();
        $transaction->update($payload);

        return new TransactionResource($transaction->refresh());
    }

    /**
     * Delete a specific transaction.
     * SECURE: Checks for ownership via the AuthorizeUser middleware.
     */
    public function destroy(Transaction $transaction): Response
    {
        $transaction->delete();

        return response()->noContent();
    }
}
