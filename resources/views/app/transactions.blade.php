<x-app-layout title="Entries">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold tracking-tight">Entries</h1>
        <button type="button" x-on:click="$dispatch('open-modal', 'quick-entry')" class="btn btn-primary hidden md:inline-flex">
            Add entry
        </button>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-out/30 bg-out-dim px-4 py-3 text-sm text-out">
            <p class="font-medium">That didn't save.</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('transactions') }}" class="sheet mb-5 flex flex-wrap items-end gap-3 px-4 py-3">
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="month">Month</label>
            <select id="month" name="month" class="input" onchange="this.form.submit()">
                <option value="">All months</option>
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}" @selected($filters['month'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-[7rem] flex-1">
            <label class="label" for="type">Direction</label>
            <select id="type" name="type" class="input" onchange="this.form.submit()">
                <option value="">In and out</option>
                <option value="expense" @selected($filters['type'] === 'expense')>Money out</option>
                <option value="income" @selected($filters['type'] === 'income')>Money in</option>
            </select>
        </div>

        <div class="min-w-[9rem] flex-1">
            <label class="label" for="reason">Reason</label>
            <select id="reason" name="reason" class="input" onchange="this.form.submit()">
                <option value="">Any reason</option>
                @foreach ($reasons as $reason)
                    <option value="{{ $reason->id }}" @selected((string) $filters['reason'] === (string) $reason->id)>
                        {{ $reason->icon ? $reason->icon.' ' : '' }}{{ $reason->name }}
                    </option>
                @endforeach
            </select>
        </div>

        @if ($filtersActive)
            <a href="{{ route('transactions') }}" class="btn btn-ghost">Clear</a>
        @endif
        <noscript><button type="submit" class="btn btn-ghost">Apply</button></noscript>
    </form>

    {{-- Ledger --}}
    <section class="sheet overflow-hidden">
        @if ($entries->isEmpty())
            <x-ui.empty
                :title="$filtersActive ? 'Nothing matches those filters' : 'No entries yet'"
                :description="$filtersActive ? 'Try a different month or reason.' : 'Log your first spend and it will show up here.'"
            >
                @if ($filtersActive)
                    <a href="{{ route('transactions') }}" class="btn btn-ghost">Clear filters</a>
                @else
                    <button type="button" x-on:click="$dispatch('open-modal', 'quick-entry')" class="btn btn-primary">Add entry</button>
                @endif
            </x-ui.empty>
        @else
            @foreach ($grouped as $date => $dayEntries)
                @php
                    $day = \Illuminate\Support\Carbon::parse($date);
                    $dayNet = $dayEntries->where('type', 'income')->sum('amount') - $dayEntries->where('type', 'expense')->sum('amount');
                @endphp
                <div class="flex items-baseline justify-between border-b border-line bg-canvas px-5 py-2">
                    <span class="text-xs font-medium text-ink-soft">
                        @if ($day->isToday()) Today
                        @elseif ($day->isYesterday()) Yesterday
                        @else {{ $day->format('D, j M Y') }}
                        @endif
                    </span>
                    <span class="text-xs tabular {{ $dayNet < 0 ? 'text-ink-faint' : 'text-accent' }}">
                        {{ $dayNet < 0 ? '−' : '+' }}{{ number_format(abs($dayNet), 2) }}
                    </span>
                </div>

                <ul class="divide-y divide-line border-b border-line last:border-b-0">
                    @foreach ($dayEntries as $entry)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-canvas text-sm">
                                    {{ $entry->category->icon ?: '•' }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm">{{ $entry->category->name ?? 'Uncategorised' }}</span>
                                    @if ($entry->description)
                                        <span class="block truncate text-xs text-ink-faint">{{ $entry->description }}</span>
                                    @endif
                                </span>
                            </span>

                            <span class="flex shrink-0 items-center gap-3">
                                <x-ui.money :amount="$entry->amount" :type="$entry->type" class="text-sm" />
                                <button
                                    type="button"
                                    class="text-xs text-ink-faint hover:text-ink"
                                    x-on:click="$dispatch('edit-entry', @js([
                                        'id' => $entry->id,
                                        'amount' => (string) $entry->amount,
                                        'type' => $entry->type,
                                        'date' => $entry->date->toDateString(),
                                        'description' => $entry->description ?? '',
                                        'category_id' => (string) $entry->category_id,
                                    ]))"
                                >Edit</button>
                                <x-ui.delete-button :action="route('transactions.destroy', $entry)" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </section>

    @if ($entries->hasPages())
        <div class="mt-4 flex items-center justify-between text-sm">
            @if ($entries->onFirstPage())
                <span class="text-ink-faint">Newer</span>
            @else
                <a href="{{ $entries->previousPageUrl() }}" class="text-accent underline underline-offset-2">Newer</a>
            @endif

            <span class="text-xs text-ink-faint">Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>

            @if ($entries->hasMorePages())
                <a href="{{ $entries->nextPageUrl() }}" class="text-accent underline underline-offset-2">Older</a>
            @else
                <span class="text-ink-faint">Older</span>
            @endif
        </div>
    @endif

    {{-- Edit modal, populated by the row buttons --}}
    <div
        x-data="{ open: false, entry: { id: null, amount: '', type: 'expense', date: '', description: '', category_id: '' } }"
        x-on:edit-entry.window="entry = { ...$event.detail }; open = true"
        x-on:keydown.escape.window="open = false"
    >
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-ink/40" x-on:click="open = false"></div>
            <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-t-xl border border-line bg-surface sm:rounded-xl">
                <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                    <h2 class="text-base font-semibold">Edit entry</h2>
                    <button type="button" x-on:click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('transactions') }}/' + entry.id" class="px-5 py-4">
                    @csrf
                    @method('PUT')

                    <div class="mb-4 grid grid-cols-2 gap-2">
                        <input type="hidden" name="type" :value="entry.type">
                        <button type="button" x-on:click="entry.type = 'expense'"
                                class="rounded-lg border px-3 py-2 text-sm"
                                :class="entry.type === 'expense' ? 'border-out bg-out-dim text-out font-medium' : 'border-line text-ink-soft'">
                            Money out
                        </button>
                        <button type="button" x-on:click="entry.type = 'income'"
                                class="rounded-lg border px-3 py-2 text-sm"
                                :class="entry.type === 'income' ? 'border-accent bg-accent-dim text-accent font-medium' : 'border-line text-ink-soft'">
                            Money in
                        </button>
                    </div>

                    <div class="mb-4">
                        <label class="label" for="edit-amount">Amount</label>
                        <input id="edit-amount" class="input" name="amount" type="number" step="0.01" min="0.01" x-model="entry.amount" required>
                    </div>

                    <div class="mb-4">
                        <label class="label" for="edit-reason">Reason</label>
                        <select id="edit-reason" class="input" name="category_id" x-model="entry.category_id">
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->icon ? $reason->icon.' ' : '' }}{{ $reason->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="label" for="edit-date">Date</label>
                        <input id="edit-date" class="input" name="date" type="date" x-model="entry.date" required>
                    </div>

                    <div class="mb-4">
                        <label class="label" for="edit-note">Note</label>
                        <input id="edit-note" class="input" name="description" x-model="entry.description" placeholder="Optional">
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Save changes</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
