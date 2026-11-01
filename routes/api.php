<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Budgets\Http\Controllers\BudgetController;
use App\Modules\Categories\Http\Controllers\CategoryController;
use App\Modules\Transactions\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');


// These routes are protected by Sanctum.
// A user MUST send a valid Bearer Token to access them.
// Names are prefixed with `api.` so apiResource doesn't claim the same route
// names as the Blade web routes (e.g. `transactions.store`) and hijack them.
Route::middleware('auth:sanctum')->name('api.')->group(function () {
    // Existing resource routes
    Route::apiResource('categories', CategoryController::class)
        ->middleware('owner:category');

    Route::apiResource('transactions', TransactionController::class)
        ->middleware('owner:transaction');

    Route::apiResource('budgets', BudgetController::class)
        ->middleware('owner:budget');

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-other-devices', [AuthController::class, 'logoutOtherDevices']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    // Delete account (soft delete)
    Route::delete('/user', [AuthController::class, 'destroy']);

    // get
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Update user profile
    Route::put('/user', [AuthController::class, 'updateProfile']);
});
