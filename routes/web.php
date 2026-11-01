<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\SessionController;
use App\Modules\Budgets\Http\Controllers\BudgetPageController;
use App\Modules\Categories\Http\Controllers\CategoryPageController;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Transactions\Http\Controllers\TransactionPageController;
use App\Modules\Users\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [SessionController::class, 'login'])->name('login.store');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [SessionController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/transactions', [TransactionPageController::class, 'index'])->name('transactions');
    Route::post('/transactions', [TransactionPageController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionPageController::class, 'update'])
        ->middleware('owner:transaction')->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionPageController::class, 'destroy'])
        ->middleware('owner:transaction')->name('transactions.destroy');

    Route::get('/budgets', [BudgetPageController::class, 'index'])->name('budgets');
    Route::post('/budgets', [BudgetPageController::class, 'store'])->name('budgets.store');
    Route::put('/budgets/{budget}', [BudgetPageController::class, 'update'])
        ->middleware('owner:budget')->name('budgets.update');
    Route::delete('/budgets/{budget}', [BudgetPageController::class, 'destroy'])
        ->middleware('owner:budget')->name('budgets.destroy');

    Route::get('/reasons', [CategoryPageController::class, 'index'])->name('reasons');
    Route::post('/reasons', [CategoryPageController::class, 'store'])->name('reasons.store');
    Route::put('/reasons/{category}', [CategoryPageController::class, 'update'])
        ->middleware('owner:category')->name('reasons.update');
    Route::delete('/reasons/{category}', [CategoryPageController::class, 'destroy'])
        ->middleware('owner:category')->name('reasons.destroy');

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
