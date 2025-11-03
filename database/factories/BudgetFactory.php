<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Budget;
use App\Models\Foreign;

class BudgetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Budget::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => Foreign::factory(),
            'category_id' => Foreign::factory(),
            'limit' => fake()->randomFloat(2, 0, 99999999.99),
            'period' => fake()->randomElement(["'monthly'","'yearly'"]),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
        ];
    }
}
