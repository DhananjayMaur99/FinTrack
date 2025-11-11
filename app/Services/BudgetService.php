<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use App\Models\Transaction; // ensure this exists

class BudgetService
{
    /**
     * Create a budget for a user.
     *
     * @param  User  $user
     * @param  array  $payload
     * @return Budget
     */
    public function createBudgetForUser(User $user, array $payload): Budget
    {
        $budget = $user->budgets()->create($payload);
        return $budget->refresh();
    }

    /**
     * Update a budget.
     *
     * @param  Budget  $budget
     * @param  array  $payload
     * @return Budget
     */
    public function updateBudget(Budget $budget, array $payload): Budget
    {
        // Ensure category cannot be changed via service payloads.
        if (array_key_exists('category_id', $payload)) {
            unset($payload['category_id']);
        }

        $budget->fill($payload)->save();
        return $budget->refresh();
    }

    /**
     * Delete a budget.
     *
     * @param  Budget  $budget
     * @return void
     */
    public function deleteBudget(Budget $budget): void
    {
        $budget->delete();
    }

    /**
     * Compute spent amount within the budget's date range and category.
     *
     * @param  Budget  $budget
     * @return float
     */
    protected function calculateSpentForRange(Budget $budget): float
    {
        $user = $budget->user; // assumes Budget belongsTo User
        if (! $user) {
            return 0.0;
        }

        $query = Transaction::query()
            ->where('user_id', $user->id)
            ->where('category_id', $budget->category_id);

        // Use local date field; adjust if your column differs.
        if ($budget->start_date) {
            $query->whereDate('date_local', '>=', $budget->start_date->toDateString());
        }
        if ($budget->end_date) {
            $query->whereDate('date_local', '<=', $budget->end_date->toDateString());
        }

        return (float) $query->sum('amount');
    }

    /**
     * Calculate progress stats for a budget.
     *
     * @return array{
     *   limit: float,
     *   spent: float,
     *   remaining: float,
     *   progress_percent: float,
     *   is_over_budget: bool
     * }
     */
    public function getBudgetProgress(Budget $budget): array
    {
        $limit = (float) $budget->limit;
        $spent = $this->calculateSpentForRange($budget);
        $remaining = max(0, $limit - $spent);
        $percent = $limit > 0 ? round(($spent / $limit) * 100, 2) : 0;

        return [
            'limit'            => $limit,
            'spent'            => $spent,
            'remaining'        => $remaining,
            'progress_percent' => $percent,
            'is_over_budget'   => $spent > $limit,
        ];
    }
}
