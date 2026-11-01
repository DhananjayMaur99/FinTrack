<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Modules\Categories\Http\Requests\CategoryStoreRequest;
use App\Modules\Categories\Http\Requests\CategoryUpdateRequest;
use App\Modules\Categories\Http\Resources\CategoryCollection;
use App\Modules\Categories\Http\Resources\CategoryResource;
use App\Modules\Categories\Models\Category;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the user's categories.
     */
    public function index(Request $request): CategoryCollection
    {
        //fetch categories of authenticated user.
        $categories = $request->user()->categories;

        return new CategoryCollection($categories);
    }

    /**
     * Store a new category, scoped to the logged-in user.
     */
    public function store(CategoryStoreRequest $request): CategoryResource
    {

        $user = $request->user();

        $category = $user->categories()->create($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Display a specific category, if the user owns it.
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    /**
     * Update a specific category, if the user owns it.
     */
    public function update(CategoryUpdateRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Delete a specific category, if the user owns it.
     */
    public function destroy(Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }
}
