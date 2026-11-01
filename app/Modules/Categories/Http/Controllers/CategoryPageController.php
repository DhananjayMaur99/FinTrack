<?php

namespace App\Modules\Categories\Http\Controllers;

use App\Modules\Categories\Models\Category;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Renders the "Expense Reasons" screen as server-rendered Blade — the
 * user-facing name for what the data layer calls a Category (see
 * CategoryController for the JSON API equivalent).
 */
class CategoryPageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');
        $start = Carbon::now($timezone)->startOfMonth()->toDateString();
        $end = Carbon::now($timezone)->endOfMonth()->toDateString();

        // Usage stats make the list useful rather than just a list of words.
        $usage = $user->transactions()
            ->whereBetween('date', [$start, $end])
            ->selectRaw('category_id, type, count(*) as entries, sum(amount) as total')
            ->groupBy('category_id', 'type')
            ->get()
            ->groupBy('category_id');

        $reasons = $user->categories()->orderBy('name')->get()->map(function (Category $reason) use ($usage) {
            $rows = $usage->get($reason->id, collect());

            return [
                'reason' => $reason,
                'entries' => (int) $rows->sum('entries'),
                'out' => (float) $rows->firstWhere('type', 'expense')?->total,
                'in' => (float) $rows->firstWhere('type', 'income')?->total,
            ];
        });

        return view('app.reasons', [
            'reasons' => $reasons,
            'monthLabel' => Carbon::now($timezone)->format('F'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->categories()->create($this->validated($request, $user->id));

        return back()->with('status', 'Reason added.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $request->user()->id, $category->id));

        return back()->with('status', 'Reason updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return back()->with('status', 'Reason deleted. Past entries keep it for history.');
    }

    private function validated(Request $request, int $userId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('user_id', $userId))
                    ->ignore($ignoreId),
            ],
            'icon' => ['nullable', 'string', 'max:8'],
        ]);
    }
}
