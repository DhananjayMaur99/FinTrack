<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetStoreRequest;
use App\Http\Requests\BudgetUpdateRequest;
use App\Http\Resources\BudgetCollection;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\UpdateBudgetRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BudgetController extends Controller
{
    public function index(Request $request): BudgetCollection
    {
        $budgets = Budget::all();

        return new BudgetCollection($budgets);
    }

    public function store(BudgetStoreRequest $request): BudgetResource
    {
        $budget = Budget::create($request->validated());

        return new BudgetResource($budget);
    }

    public function show(Request $request, Budget $budget): BudgetResource
    {
        return new BudgetResource($budget);
    }

    public function update(BudgetUpdateRequest $request, Budget $budget): BudgetResource
    {
        $budget->update($request->validated());

        return new BudgetResource($budget);
    }

    public function destroy(Request $request, Budget $budget): Response
    {
        $budget->delete();

        return response()->noContent();
    }

    public function requests(Request $request): Response
    {
        $request->session()->store('StoreBudgetRequest', $StoreBudgetRequest);

        $updateBudgetRequest->update([]);
    }
}
