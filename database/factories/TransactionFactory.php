<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Foreign;
use App\Models\Transaction;

class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => Foreign::factory(),
            'category_id' => Foreign::factory(),
            'amount' => fake()->randomFloat(2, 0, 99999999.99),
            'description' => fake()->text(),
            'date' => fake()->date(),
        ];
    }
}
