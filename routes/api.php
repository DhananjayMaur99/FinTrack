<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// These routes are protected by Sanctum.
// A user MUST send a valid Bearer Token to access them.
Route::middleware('auth:sanctum')->group(function () {
    // Existing resource routes
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('budgets', BudgetController::class);

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    // Delete account (soft delete)
    Route::delete('/user', [AuthController::class, 'destroy']);

    // get
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Update user profile
    Route::patch('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
});
