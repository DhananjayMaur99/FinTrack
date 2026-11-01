<?php

namespace App\Modules\Transactions\Http\Controllers;

use App\Modules\Transactions\Models\Transaction;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Renders the Entries screen as server-rendered Blade — distinct from
 * TransactionController, which serves the JSON API.
 */
class TransactionPageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $timezone = $user->timezone ?: config('app.timezone', 'UTC');

        $filters = [
            'month' => $request->query('month', ''),
            'type' => in_array($request->query('type'), ['income', 'expense'], true) ? $request->query('type') : '',
            'reason' => $request->query('reason', ''),
        ];

        $query = $user->transactions()->with('category');

        if ($filters['month'] !== '') {
            try {
                $month = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
                $query->whereBetween('date', [
                    $month->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                ]);
            } catch (\Throwable) {
                $filters['month'] = '';
            }
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if ($filters['reason'] !== '') {
            $query->where('category_id', $filters['reason']);
        }

        $entries = $query->latest('date')->latest('id')->paginate(25)->withQueryString();

        return view('app.transactions', [
            'entries' => $entries,
            'grouped' => $entries->getCollection()->groupBy(fn (Transaction $t) => $t->date->toDateString()),
            'reasons' => $user->categories()->orderBy('name')->get(),
            'filters' => $filters,
            'months' => $this->availableMonths($user, $timezone),
            'filtersActive' => $filters['month'] !== '' || $filters['type'] !== '' || $filters['reason'] !== '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->transactions()->create($this->validated($request, $user->id));

        return back()->with('status', 'Entry added.');
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $transaction->update($this->validated($request, $request->user()->id));

        return back()->with('status', 'Entry updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return back()->with('status', 'Entry deleted.');
    }

    private function validated(Request $request, int $userId): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:income,expense'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
        ]);
    }

    /**
     * Months the user actually has entries in, newest first, for the filter.
     */
    private function availableMonths($user, string $timezone): array
    {
        return $user->transactions()
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym")
            ->distinct()
            ->orderByDesc('ym')
            ->pluck('ym')
            ->mapWithKeys(fn ($ym) => [$ym => Carbon::createFromFormat('Y-m', $ym)->format('F Y')])
            ->all();
    }
}
