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

        $this->authorize('view', $category);

        return new CategoryResource($category);
    }

    /**
     * Update a specific category, if the user owns it.
     */
    public function update(CategoryUpdateRequest $request, Category $category): CategoryResource
    {

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

        $this->authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }

}
