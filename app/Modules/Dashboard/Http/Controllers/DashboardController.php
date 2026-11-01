<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Budgets\Models\Budget;
use App\Modules\Budgets\Services\BudgetService;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BudgetService $budgetService): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');
        $thisMonth = Carbon::now($timezone)->startOfMonth();

        $month = $this->resolveMonth($request->query('month'), $thisMonth);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $entries = $user->transactions()
            ->with('category')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $expenses = $entries->where('type', 'expense');
        $income = (float) $entries->where('type', 'income')->sum('amount');
        $spent = (float) $expenses->sum('amount');

        $breakdown = $expenses
            ->groupBy('category_id')
            ->map(fn ($group) => [
                'reason' => $group->first()->category,
                'total' => (float) $group->sum('amount'),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $budgets = $user->budgets()->with('category')->orderByDesc('id')->take(3)->get()
            ->map(fn (Budget $budget) => [
                'budget' => $budget,
                'stats' => $budgetService->getBudgetProgress($budget),
                'daysLeft' => $budget->end_date
                    ? (int) max(0, Carbon::now($timezone)->startOfDay()->diffInDays($budget->end_date, false))
                    : null,
            ]);

        return view('app.dashboard', [
            'month' => $month,
            'isCurrentMonth' => $month->equalTo($thisMonth),
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'income' => $income,
            'spent' => $spent,
            'net' => $income - $spent,
            'breakdown' => $breakdown,
            'biggestSlice' => (float) ($breakdown->max('total') ?: 0),
            'budgets' => $budgets,
            'recent' => $user->transactions()
                ->with('category')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->latest('date')
                ->latest('id')
                ->take(5)
                ->get(),
            'entryCount' => $entries->count(),
        ]);
    }

    private function resolveMonth(?string $requested, Carbon $thisMonth): Carbon
    {
        if (! $requested) {
            return $thisMonth->copy();
        }

        try {
            $month = Carbon::createFromFormat('Y-m', $requested)->startOfMonth();
        } catch (\Throwable) {
            return $thisMonth->copy();
        }

        // Nothing to show beyond the current month.
        return $month->greaterThan($thisMonth) ? $thisMonth->copy() : $month;
    }
}
