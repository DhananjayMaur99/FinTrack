<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'limit' => $this->faker->randomFloat(2, 100, 50000), // Reasonable budgets: $100 to $50,000
            'period' => $this->faker->randomElement(['monthly', 'yearly']),
            'start_date' => $this->faker->date(),
        ];
    }
}
