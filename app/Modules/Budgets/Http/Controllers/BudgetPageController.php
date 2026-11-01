<?php

namespace App\Modules\Budgets\Http\Controllers;

use App\Modules\Budgets\Models\Budget;
use App\Modules\Budgets\Services\BudgetService;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Renders the Budgets screen as server-rendered Blade — distinct from
 * BudgetController, which serves the JSON API.
 */
class BudgetPageController extends Controller
{
    public function index(Request $request, BudgetService $budgetService): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');
        $today = Carbon::now($timezone)->startOfDay();

        $budgets = $user->budgets()->with('category')->orderByDesc('id')->get()
            ->map(fn (Budget $budget) => [
                'budget' => $budget,
                'stats' => $budgetService->getBudgetProgress($budget),
                'daysLeft' => $budget->end_date ? (int) max(0, $today->diffInDays($budget->end_date, false)) : null,
                'expired' => $budget->end_date ? $budget->end_date->lessThan($today) : false,
            ]);

        return view('app.budgets', [
            'budgets' => $budgets,
            'reasons' => $user->categories()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, BudgetService $budgetService): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $user->id)->whereNull('deleted_at'),
            ],
            'limit' => ['required', 'numeric', 'min:0.01'],
            'period' => ['required', 'in:weekly,monthly,yearly'],
            'start_date' => ['required', 'date'],
        ]);

        $data['end_date'] = $this->computeEndDate($data['start_date'], $data['period']);

        $budgetService->createBudgetForUser($user, $data);

        return back()->with('status', 'Budget set.');
    }

    public function update(Request $request, Budget $budget, BudgetService $budgetService): RedirectResponse
    {
        $data = $request->validate([
            'limit' => ['required', 'numeric', 'min:0.01'],
            'period' => ['required', 'in:weekly,monthly,yearly'],
            'start_date' => ['required', 'date'],
        ]);

        $data['end_date'] = $this->computeEndDate($data['start_date'], $data['period']);

        $budgetService->updateBudget($budget, $data);

        return back()->with('status', 'Budget updated.');
    }

    public function destroy(Budget $budget, BudgetService $budgetService): RedirectResponse
    {
        $budgetService->deleteBudget($budget);

        return back()->with('status', 'Budget deleted.');
    }

    private function computeEndDate(string $start, string $period): string
    {
        $startDate = Carbon::parse($start)->startOfDay();

        // NoOverflow keeps a 31st start date from landing two months out.
        return match ($period) {
            'weekly' => $startDate->copy()->addWeek()->subDay()->toDateString(),
            'yearly' => $startDate->copy()->addYearNoOverflow()->subDay()->toDateString(),
            default => $startDate->copy()->addMonthNoOverflow()->subDay()->toDateString(),
        };
    }
}
