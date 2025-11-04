<?php

namespace App\Http\Controllers;

// 1. Import all the classes we need
use App\Http\Requests\BudgetStoreRequest;
use App\Http\Requests\BudgetUpdateRequest; // <-- Import our new service
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
    public function store(BudgetStoreRequest $request): BudgetResource
    {
        $this->authorize('create', Budget::class);
        $budget = $request->user()->budgets()->create($request->validated());

        // Return a simple resource on create
        return new BudgetResource($budget);
    }

    /**
     * Display a specific budget and its progress.
     */
    public function show(Budget $budget, BudgetService $budgetService): BudgetResource
    {
        $this->authorize('view', $budget);
        $progressStats = $budgetService->getBudgetProgress($budget);

        // Pass both the model and the stats to the resource
        // This works with the __construct() in your BudgetResource
        return new BudgetResource($budget, $progressStats);
    }

    /**
     * Update a specific budget.
     */
    public function update(BudgetUpdateRequest $request, Budget $budget, BudgetService $budgetService): BudgetResource
    {
        $this->authorize('update', $budget);
        $budget->update($request->validated());

        // (THE FIX)
        // Now that the budget is updated (e.g., new limit),
        // we re-run the 'show' logic to get a fresh response
        // with the *new* progress_stats.
        return $this->show($budget, $budgetService);
    }

    /**
     * Delete a specific budget.
     */
    public function destroy(Budget $budget): Response
    {
        $this->authorize('delete', $budget);
        $budget->delete();

        return response()->noContent();
    }
}
