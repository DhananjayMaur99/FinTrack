<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    /**
     * Determine whether the user can view any models.
     * We don't have an admin panel, so this is 'true'
     * as the controller will scope the list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * SECURE: The user's ID must match the budget's user_id.
     */
    public function view(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create a budget.
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * SECURE: The user's ID must match the budget's user_id.
     */
    public function update(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     * SECURE: The user's ID must match the budget's user_id.
     */
    public function delete(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Budget $budget): bool
    {
        // This rule is for soft-deleted models
        return $user->id === $budget->user_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Budget $budget): bool
    {
        // This rule is for soft-deleted models
        return $user->id === $budget->user_id;
    }
}
