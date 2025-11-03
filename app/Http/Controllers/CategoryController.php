<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\UpdateCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(Request $request): CategoryCollection
    {
        $categories = Category::all();

        return new CategoryCollection($categories);
    }

    public function store(CategoryStoreRequest $request): CategoryResource
    {
        $category = Category::create($request->validated());

        return new CategoryResource($category);
    }

    public function show(Request $request, Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(CategoryUpdateRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Request $request, Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }

    public function requests(Request $request): Response
    {
        $request->session()->store('StoreCategoryRequest', $StoreCategoryRequest);

        $updateCategoryRequest->update([]);
    }
}
