<?php

namespace Database\Seeders;

use App\Modules\Categories\Models\Category;
use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Builds a coherent three-month ledger for the demo account: real reasons,
     * a monthly salary, recurring bills and day-to-day spending — so every
     * screen (dashboard totals, budget gauges, breakdowns) has something true
     * to show.
     */
    private const REASONS = [
        ['name' => 'Groceries', 'icon' => '🛒', 'min' => 350, 'max' => 2600, 'perMonth' => 7],
        ['name' => 'Eating out', 'icon' => '🍜', 'min' => 180, 'max' => 1900, 'perMonth' => 6],
        ['name' => 'Transport', 'icon' => '🚌', 'min' => 60, 'max' => 700, 'perMonth' => 8],
        ['name' => 'Shopping', 'icon' => '🛍️', 'min' => 500, 'max' => 5200, 'perMonth' => 3],
        ['name' => 'Health', 'icon' => '💊', 'min' => 200, 'max' => 2800, 'perMonth' => 1],
        ['name' => 'Entertainment', 'icon' => '🎬', 'min' => 200, 'max' => 1400, 'perMonth' => 2],
    ];

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );

        $user->transactions()->forceDelete();
        $user->budgets()->forceDelete();
        $user->categories()->forceDelete();

        $reasons = [];
        foreach ([...self::REASONS, ['name' => 'Rent', 'icon' => '🏠'], ['name' => 'Utilities', 'icon' => '💡'], ['name' => 'Salary', 'icon' => '💰']] as $spec) {
            $reasons[$spec['name']] = $user->categories()->create([
                'name' => $spec['name'],
                'icon' => $spec['icon'],
            ]);
        }

        $today = Carbon::today();

        for ($monthsAgo = 2; $monthsAgo >= 0; $monthsAgo--) {
            // Start from the 1st before subtracting: subtracting months from a
            // 29th–31st overflows into the following month.
            $month = $today->copy()->startOfMonth()->subMonths($monthsAgo);

            $this->entry($user, $reasons['Salary'], 'income', 65000, 'Monthly salary', $month->copy()->startOfMonth(), $today);
            $this->entry($user, $reasons['Rent'], 'expense', 18500, 'Monthly rent', $month->copy()->addDays(2), $today);
            $this->entry($user, $reasons['Utilities'], 'expense', random_int(900, 2400), 'Electricity and water', $month->copy()->addDays(6), $today);

            foreach (self::REASONS as $spec) {
                for ($i = 0; $i < $spec['perMonth']; $i++) {
                    $day = $month->copy()->addDays(random_int(0, $month->daysInMonth - 1));
                    $this->entry($user, $reasons[$spec['name']], 'expense', random_int($spec['min'], $spec['max']), null, $day, $today);
                }
            }
        }

        $this->seedBudgets($user, $reasons, $today);
    }

    private function entry(User $user, Category $reason, string $type, float $amount, ?string $note, Carbon $date, Carbon $today): void
    {
        if ($date->greaterThan($today)) {
            return;
        }

        $user->transactions()->create([
            'category_id' => $reason->id,
            'type' => $type,
            'amount' => $amount,
            'description' => $note,
            'date' => $date->toDateString(),
        ]);
    }

    /**
     * Limits are derived from what was actually spent this month so the demo
     * shows one healthy budget, one close to its limit, and one overspent.
     */
    private function seedBudgets(User $user, array $reasons, Carbon $today): void
    {
        $start = $today->copy()->startOfMonth();
        $end = $today->copy()->endOfMonth();

        $ratios = ['Groceries' => 0.45, 'Eating out' => 0.88, 'Shopping' => 1.25];

        foreach ($ratios as $name => $ratio) {
            $spent = (float) $user->transactions()
                ->where('category_id', $reasons[$name]->id)
                ->where('type', 'expense')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');

            $user->budgets()->create([
                'category_id' => $reasons[$name]->id,
                'limit' => max(round(($spent ?: 5000) / $ratio, -2), 500),
                'period' => 'monthly',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);
        }
    }
}
