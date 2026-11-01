<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetStoreRequest;
use App\Http\Requests\BudgetUpdateRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BudgetController extends Controller
{
    /**
     * Display a list of the user's budgets.
     */
    public function index(Request $request, BudgetService $budgetService): AnonymousResourceCollection
    {
        $budgets = $request->user()
            ->budgets()
            ->with('category')
            ->orderByDesc('id')
            ->get();

        // Wrap each budget with its computed stats
        $resources = $budgets->map(fn($b) => new BudgetResource($b, $budgetService->getBudgetProgress($b)));

        // Return as a resource collection (keeps pagination option open later)
        return BudgetResource::collection($resources);
    }

    public function store(BudgetStoreRequest $request, BudgetService $budgetService): BudgetResource
    {
        $budget = $budgetService->createBudgetForUser(
            $request->user(),
            $request->validated()
        );

        return new BudgetResource($budget, $budgetService->getBudgetProgress($budget));
    }

    public function show(Budget $budget, BudgetService $budgetService): BudgetResource
    {
        $budget->load('category');

        return new BudgetResource($budget, $budgetService->getBudgetProgress($budget));
    }

    public function update(BudgetUpdateRequest $request, Budget $budget, BudgetService $budgetService): BudgetResource
    {
        $budget = $budgetService->updateBudget($budget, $request->validated());
        $budget->load('category');

        return new BudgetResource($budget, $budgetService->getBudgetProgress($budget));
    }

    public function destroy(Budget $budget, BudgetService $budgetService): Response
    {
        $budgetService->deleteBudget($budget);

        return response()->noContent();
    }
}
