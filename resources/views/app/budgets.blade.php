<x-app-layout title="Budgets">
    <div class="mb-1 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold tracking-tight">Budgets</h1>
        <button type="button" x-on:click="$dispatch('open-modal', 'add-budget')" class="btn btn-primary">
            New budget
        </button>
    </div>
    <p class="mb-5 text-sm text-ink-soft">A spending cap per reason, or one cap across everything.</p>

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-out/30 bg-out-dim px-4 py-3 text-sm text-out">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($budgets->isEmpty())
        <div class="sheet">
            <x-ui.empty title="No budgets yet" description="Set a cap and FinTrack will track what's left as you log entries.">
                <button type="button" x-on:click="$dispatch('open-modal', 'add-budget')" class="btn btn-primary">Set your first budget</button>
            </x-ui.empty>
        </div>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($budgets as $row)
                @php($budget = $row['budget'])
                @php($stats = $row['stats'])
                <li class="sheet px-5 py-4">
                    <div class="mb-2.5 flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-canvas text-base">
                                {{ $budget->category->icon ?? '◎' }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $budget->category->name ?? 'Everything' }}</p>
                                <p class="text-xs capitalize text-ink-faint">
                                    {{ $budget->period }}
                                    @if ($row['expired']) · ended {{ $budget->end_date->format('j M') }} @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <button
                                type="button"
                                class="text-xs text-ink-faint hover:text-ink"
                                x-on:click="$dispatch('edit-budget', @js([
                                    'id' => $budget->id,
                                    'limit' => (string) $budget->limit,
                                    'period' => $budget->period,
                                    'start_date' => $budget->start_date->toDateString(),
                                    'name' => $budget->category->name ?? 'Everything',
                                ]))"
                            >Edit</button>
                            <x-ui.delete-button :action="route('budgets.destroy', $budget)" />
                        </div>
                    </div>

                    <div class="mb-1.5 flex items-baseline justify-between">
                        <span class="text-lg tabular {{ $stats['is_over_budget'] ? 'text-out' : 'text-ink' }}">
                            {{ number_format($stats['spent'], 2) }}
                        </span>
                        <span class="text-sm tabular text-ink-faint">of {{ number_format($stats['limit'], 2) }}</span>
                    </div>

                    <x-ui.gauge :percent="$stats['progress_percent']" :over="$stats['is_over_budget']" />

                    <div class="mt-2 flex items-baseline justify-between text-xs">
                        <span class="{{ $stats['is_over_budget'] ? 'font-medium text-out' : 'text-ink-soft' }}">
                            @if ($stats['is_over_budget'])
                                {{ number_format($stats['spent'] - $stats['limit'], 2) }} over budget
                            @else
                                {{ number_format($stats['remaining'], 2) }} still available
                            @endif
                        </span>
                        <span class="text-ink-faint">
                            @if ($row['daysLeft'] === null) no end date
                            @elseif ($row['expired']) period over
                            @elseif ($row['daysLeft'] === 0) ends today
                            @elseif ($row['daysLeft'] === 1) 1 day left
                            @else {{ $row['daysLeft'] }} days left
                            @endif
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Add --}}
    <x-ui.modal name="add-budget" title="New budget">
        <form method="POST" action="{{ route('budgets.store') }}">
            @csrf
            <x-ui.field label="Applies to" name="category_id" type="select">
                <option value="">Everything</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->id }}">{{ $reason->icon ? $reason->icon.' ' : '' }}{{ $reason->name }}</option>
                @endforeach
            </x-ui.field>

            <x-ui.field label="Limit" name="limit" type="number" step="0.01" min="0.01" placeholder="0.00" required />

            <x-ui.field label="Resets" name="period" type="select">
                <option value="weekly">Weekly</option>
                <option value="monthly" selected>Monthly</option>
                <option value="yearly">Yearly</option>
            </x-ui.field>

            <x-ui.field label="Starting" name="start_date" type="date" :value="now()->startOfMonth()->toDateString()" required />

            <button type="submit" class="btn btn-primary w-full">Set budget</button>
        </form>
    </x-ui.modal>

    {{-- Edit --}}
    <div
        x-data="{ open: false, budget: { id: null, limit: '', period: 'monthly', start_date: '', name: '' } }"
        x-on:edit-budget.window="budget = { ...$event.detail }; open = true"
        x-on:keydown.escape.window="open = false"
    >
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-ink/40" x-on:click="open = false"></div>
            <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-t-xl border border-line bg-surface sm:rounded-xl">
                <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                    <h2 class="text-base font-semibold">Edit budget</h2>
                    <button type="button" x-on:click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('budgets') }}/' + budget.id" class="px-5 py-4">
                    @csrf
                    @method('PUT')

                    <p class="mb-4 text-sm text-ink-soft">
                        Applies to <span class="font-medium text-ink" x-text="budget.name"></span>. The reason can't be changed once set.
                    </p>

                    <div class="mb-4">
                        <label class="label" for="edit-budget-limit">Limit</label>
                        <input id="edit-budget-limit" class="input" name="limit" type="number" step="0.01" min="0.01" x-model="budget.limit" required>
                    </div>

                    <div class="mb-4">
                        <label class="label" for="edit-budget-period">Resets</label>
                        <select id="edit-budget-period" class="input" name="period" x-model="budget.period">
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="label" for="edit-budget-start">Starting</label>
                        <input id="edit-budget-start" class="input" name="start_date" type="date" x-model="budget.start_date" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Save changes</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
