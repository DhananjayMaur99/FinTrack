<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Ensure test user exists (idempotent)
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        // seed categories/transactions/budgets using the ensured user
        Category::factory()->count(5)->for($user)->create();
        Transaction::factory()->count(20)->for($user)->create();
        Budget::factory()->count(3)->for($user)->create();
    }
}
