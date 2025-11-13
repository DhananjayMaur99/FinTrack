<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});


// These routes are protected by Sanctum.
// A user MUST send a valid Bearer Token to access them.
// Rate limited to 60 requests per minute

Route::middleware(['throttle:60,1', 'auth:sanctum'])->group(function () {
    // Existing resource routes
    Route::apiResource('categories', CategoryController::class)
        ->middleware('owner:category');

    Route::apiResource('transactions', TransactionController::class)
        ->middleware('owner:transaction');

    Route::apiResource('budgets', BudgetController::class)
        ->middleware('owner:budget');

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    // Delete account (soft delete)
    Route::delete('/user', [AuthController::class, 'destroy']);

    // // get
    // Route::get('/user', function (Request $request) {
    //     return $request->user();
    // });

    // Update user profile
    Route::put('/user', [AuthController::class, 'updateProfile']);
});
