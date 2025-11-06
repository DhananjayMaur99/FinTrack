<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- Protected Routes ---
// These routes are protected by Sanctum.
// A user MUST send a valid Bearer Token to access them.
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    // Delete account (soft delete)
    Route::delete('/user', [AuthController::class, 'destroy']);

    // get
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Our existing resource routes
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('budgets', BudgetController::class);

    // Get the currently logged in user

});
