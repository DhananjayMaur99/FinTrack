<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Service class for handling complex budget-related logic.
 * Follows the rule: "All business logic MUST go in app/Services".
 */
class BudgetService
{
    /**
     * Calculate the progress and spending for a given budget.
     *
     * @param  Budget  $budget  The budget to calculate progress for.
     * @return array{
     * limit: float,
     * spent: float,
     * remaining: float,
     * progress_percent: float,
     * is_over_budget: bool
     * }
     */
    public function getBudgetProgress(Budget $budget): array
    {
        $startDate = $budget->start_date;
        $endDate = $budget->end_date ?? Carbon::now();

        // Start building the query to get all relevant transactions
        $transactionsQuery = Transaction::query()
            ->where('user_id', $budget->user_id)
            ->whereBetween('date', [$startDate, $endDate]);

        // If this budget is for a specific category, filter by it.
        // If category_id is null, it's a "total spending" budget,
        // so we don't filter by category.
        if ($budget->category_id) {
            $transactionsQuery->where('category_id', $budget->category_id);
        }

        // Sum the amount and cast to float
        $spent = (float) $transactionsQuery->sum('amount');
        $limit = (float) $budget->limit;

        // Calculate statistics
        $remaining = $limit - $spent;
        $progressPercent = ($limit > 0) ? ($spent / $limit) * 100 : 0;

        return [
            'limit' => $limit,
            'spent' => $spent,
            'remaining' => $remaining,
            'progress_percent' => round($progressPercent, 2), // Round to 2 decimal places
            'is_over_budget' => $spent > $limit,
        ];
    }
}
