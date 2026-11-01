<?php

namespace Database\Factories;

use App\Modules\Categories\Models\Category;
use App\Modules\Transactions\Models\Transaction;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'type' => 'expense',
            'amount' => $this->faker->randomFloat(2, 50, 4000),
            'description' => null,
            'date' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'type' => 'income',
            'amount' => $this->faker->randomFloat(2, 5000, 80000),
        ]);
    }
}
