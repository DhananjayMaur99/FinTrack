<?php

use Illuminate\Support\Facades\Route;


Route::get('categories/requests', [App\Http\Controllers\CategoryController::class, 'requests']);
Route::apiResource('categories', App\Http\Controllers\CategoryController::class);

Route::get('transactions/requests', [App\Http\Controllers\TransactionController::class, 'requests']);
Route::apiResource('transactions', App\Http\Controllers\TransactionController::class);

Route::get('budgets/requests', [App\Http\Controllers\BudgetController::class, 'requests']);
Route::apiResource('budgets', App\Http\Controllers\BudgetController::class);


Route::get('categories/requests', [App\Http\Controllers\CategoryController::class, 'requests']);
Route::apiResource('categories', App\Http\Controllers\CategoryController::class);

Route::get('transactions/requests', [App\Http\Controllers\TransactionController::class, 'requests']);
Route::apiResource('transactions', App\Http\Controllers\TransactionController::class);

Route::get('budgets/requests', [App\Http\Controllers\BudgetController::class, 'requests']);
Route::apiResource('budgets', App\Http\Controllers\BudgetController::class);


Route::get('categories/requests', [App\Http\Controllers\CategoryController::class, 'requests']);
Route::apiResource('categories', App\Http\Controllers\CategoryController::class);

Route::get('transactions/requests', [App\Http\Controllers\TransactionController::class, 'requests']);
Route::apiResource('transactions', App\Http\Controllers\TransactionController::class);

Route::get('budgets/requests', [App\Http\Controllers\BudgetController::class, 'requests']);
Route::apiResource('budgets', App\Http\Controllers\BudgetController::class);
