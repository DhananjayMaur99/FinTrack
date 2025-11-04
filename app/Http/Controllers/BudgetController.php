<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetStoreRequest;
use App\Http\Requests\BudgetUpdateRequest;
use App\Http\Resources\BudgetCollection;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BudgetController extends Controller
{
    /**
     * Display a paginated list of the user's budgets.
     */
    public function index(Request $request): BudgetCollection
    {
        $budgets = $request->user()->budgets()->paginate();

        return new BudgetCollection($budgets);
    }

    /**
     * Store a new budget for the user.
     */
    public function store(BudgetStoreRequest $request, BudgetService $budgetService): BudgetResource
    {
        $this->authorize('create', Budget::class);
        $budget = $budgetService->createBudgetForUser(
            $request->user(),
            $request->validated()
        );

        return $this->buildBudgetResource($budget, $budgetService);
    }

    /**
     * Display a specific budget and its progress.
     */
    public function show(Budget $budget, BudgetService $budgetService): BudgetResource
    {
        $this->authorize('view', $budget);

        return $this->buildBudgetResource($budget, $budgetService);
    }

    /**
     * Update a specific budget.
     */
    public function update(BudgetUpdateRequest $request, Budget $budget, BudgetService $budgetService): BudgetResource
    {
        $this->authorize('update', $budget);
        $budget = $budgetService->updateBudget($budget, $request->validated());

        return $this->buildBudgetResource($budget, $budgetService);
    }

    /**
     * Delete a specific budget.
     */
    public function destroy(Budget $budget, BudgetService $budgetService): Response
    {
        $this->authorize('delete', $budget);

        $budgetService->deleteBudget($budget);

        return response()->noContent();
    }

    private function buildBudgetResource(Budget $budget, BudgetService $budgetService): BudgetResource
    {
        $progressStats = $budgetService->getBudgetProgress($budget);

        return new BudgetResource($budget, $progressStats);
    }
}
