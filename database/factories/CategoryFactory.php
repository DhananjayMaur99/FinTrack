<?php

namespace Database\Factories;

use App\Modules\Categories\Models\Category;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Reasons people actually log, so seeded data reads like a real ledger.
     */
    private const REASONS = [
        ['Groceries', '🛒'],
        ['Rent', '🏠'],
        ['Transport', '🚌'],
        ['Eating out', '🍜'],
        ['Utilities', '💡'],
        ['Health', '💊'],
        ['Shopping', '🛍️'],
        ['Entertainment', '🎬'],
        ['Subscriptions', '📺'],
        ['Salary', '💰'],
    ];

    public function definition(): array
    {
        [$name, $icon] = $this->faker->unique()->randomElement(self::REASONS);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'icon' => $icon,
        ];
    }
}
