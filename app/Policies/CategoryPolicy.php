<?php

namespace App\Policies;

use App\Models\category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {

        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, category $category): bool
    {
        // The user can view the category if their ID
        // matches the category's 'user_id'.
        return $user->id === $category->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Any logged-in user is allowed to create a new category.
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, category $category): bool
    {
        // The user can update the category if they are the owner.
        return $user->id === $category->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, category $category): bool
    {
        // The user can delete the category if they are the owner.
        return $user->id === $category->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, category $category): bool
    {
        return false;
    }
}
