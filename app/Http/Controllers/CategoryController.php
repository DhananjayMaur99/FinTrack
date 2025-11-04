<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the user's categories.
     */
    public function index(Request $request): CategoryCollection
    {
        // ERROR 1 (FIXED):
        // The original code $request->auth()->user()->categories() had two errors:
        // 1. `auth()` is a global helper, not a method on `$request`.
        // 2. `...->categories()` (with parentheses) gets the query builder, not the results.
        //
        // THE FIX:
        // We get the authenticated user and return *only* their categories.
        // This is a critical security practice called "User Scoping".
        $categories = $request->user()->categories;

        return new CategoryCollection($categories);
    }

    /**
     * Store a new category, scoped to the logged-in user.
     */
    public function store(CategoryStoreRequest $request): CategoryResource
    {
        // ERROR 2 (FIXED):
        // The original code `Category::create($request->validated())` was a major
        // security hole. It would create a category but not link it to the logged-in user.
        //
        // THE FIX:
        // We use the 'categories' relationship on the User model.
        // This automatically and securely sets the 'user_id' for us.
        /** @var \App\Models\User $user */
        $user = $request->user();

        $category = $user->categories()->create($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Display a specific category, if the user owns it.
     */
    public function show(Category $category): CategoryResource
    {
        // ERROR 3 (FIXED):
        // The original code was a security hole. It would show *any* category
        // as long as the ID existed, even if it belonged to another user.
        //
        // THE FIX:
        // We must check if the authenticated user is "authorized" to see this category.
        // This code will throw a 403 Forbidden error if the user does not own the model.
        $this->authorize('view', $category);

        return new CategoryResource($category);
    }

    /**
     * Update a specific category, if the user owns it.
     */
    public function update(CategoryUpdateRequest $request, Category $category): CategoryResource
    {
        // ERROR 4 (FIXED):
        // Same security hole as 'show()'.
        //
        // THE FIX:
        // We authorize that the user is allowed to 'update' this specific category.
        $this->authorize('update', $category);

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Delete a specific category, if the user owns it.
     */
    public function destroy(Category $category): Response
    {
        // ERROR 5 (FIXED):
        // Same security hole as 'show()' and 'update()'.
        //
        // THE FIX:
        // We authorize that the user is allowed to 'delete' this specific category.
        $this->authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }

    // ERROR 6 (FIXED):
    // The 'requests' method was removed. It was not part of a resource
    // controller, was trying to use 'session' in a stateless API,
    // and contained undefined variables.
}
