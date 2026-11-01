<x-app-layout title="Overview">
    {{-- Month switcher --}}
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">{{ $month->format('F Y') }}</h1>
        <div class="flex items-center gap-1">
            <a href="{{ route('dashboard', ['month' => $previousMonth]) }}"
               class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-surface text-ink-soft hover:text-ink"
               aria-label="Previous month">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4l-6 6 6 6"/></svg>
            </a>
            @if ($isCurrentMonth)
                <span class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-canvas text-ink-faint" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4l6 6-6 6"/></svg>
                </span>
            @else
                <a href="{{ route('dashboard', ['month' => $nextMonth]) }}"
                   class="flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-surface text-ink-soft hover:text-ink"
                   aria-label="Next month">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4l6 6-6 6"/></svg>
                </a>
            @endif
        </div>
    </div>

    @if ($entryCount === 0)
        <div class="sheet">
            <x-ui.empty
                title="Nothing logged in {{ $month->format('F') }}"
                description="Add your first entry for this month and your totals, budgets and breakdown will fill in here."
            >
                <button type="button" x-on:click="$dispatch('open-modal', 'quick-entry')" class="btn btn-primary">Add entry</button>
            </x-ui.empty>
        </div>
    @else
        {{-- Headline figures --}}
        <section class="sheet mb-5 px-5 py-5">
            <p class="text-sm text-ink-soft">Left over this month</p>
            <p class="mt-1 text-4xl font-semibold tabular {{ $net < 0 ? 'text-out' : 'text-ink' }}">
                {{ $net < 0 ? '−' : '' }}{{ number_format(abs($net), 2) }}
            </p>

            <div class="mt-5 grid grid-cols-2 gap-4 border-t border-line pt-4">
                <div>
                    <p class="text-xs text-ink-faint">Money in</p>
                    <p class="mt-0.5 text-lg"><x-ui.money :amount="$income" type="income" /></p>
                </div>
                <div>
                    <p class="text-xs text-ink-faint">Money out</p>
                    <p class="mt-0.5 text-lg"><x-ui.money :amount="$spent" type="expense" /></p>
                </div>
            </div>
        </section>

        {{-- Where it went --}}
        @if ($breakdown->isNotEmpty())
            <section class="sheet mb-5">
                <div class="flex items-baseline justify-between border-b border-line px-5 py-3.5">
                    <h2 class="text-sm font-semibold">Where it went</h2>
                    <span class="text-xs text-ink-faint">Top {{ $breakdown->count() }}</span>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($breakdown as $row)
                        <li class="px-5 py-3">
                            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2 text-sm">
                                    <span class="shrink-0">{{ $row['reason']->icon ?: '•' }}</span>
                                    <span class="truncate">{{ $row['reason']->name ?? 'Uncategorised' }}</span>
                                    <span class="shrink-0 text-xs text-ink-faint">{{ $row['count'] }}×</span>
                                </span>
                                <x-ui.money :amount="$row['total']" class="shrink-0 text-sm" />
                            </div>
                            <div class="gauge-track">
                                <div class="gauge-fill bg-accent/70" style="width: {{ $biggestSlice > 0 ? round($row['total'] / $biggestSlice * 100, 1) : 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Budgets --}}
        @if ($budgets->isNotEmpty())
            <section class="sheet mb-5">
                <div class="flex items-baseline justify-between border-b border-line px-5 py-3.5">
                    <h2 class="text-sm font-semibold">Budgets</h2>
                    <a href="{{ route('budgets') }}" class="text-xs text-accent underline underline-offset-2">Manage</a>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($budgets as $row)
                        @php($stats = $row['stats'])
                        <li class="px-5 py-3.5">
                            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                                <span class="flex items-center gap-2 text-sm">
                                    <span>{{ $row['budget']->category->icon ?? '◎' }}</span>
                                    {{ $row['budget']->category->name ?? 'Overall' }}
                                </span>
                                <span class="text-sm tabular text-ink-soft">
                                    {{ number_format($stats['spent'], 0) }} / {{ number_format($stats['limit'], 0) }}
                                </span>
                            </div>
                            <x-ui.gauge :percent="$stats['progress_percent']" :over="$stats['is_over_budget']" />
                            <p class="mt-1.5 text-xs {{ $stats['is_over_budget'] ? 'text-out' : 'text-ink-faint' }}">
                                @if ($stats['is_over_budget'])
                                    {{ number_format($stats['spent'] - $stats['limit'], 2) }} over
                                @else
                                    {{ number_format($stats['remaining'], 2) }} left
                                @endif
                                @if ($row['daysLeft'] !== null)
                                    @if ($row['daysLeft'] === 0) · ends today
                                    @elseif ($row['daysLeft'] === 1) · 1 day left
                                    @else · {{ $row['daysLeft'] }} days left
                                    @endif
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Recent entries --}}
        <section class="sheet">
            <div class="flex items-baseline justify-between border-b border-line px-5 py-3.5">
                <h2 class="text-sm font-semibold">Latest entries</h2>
                <a href="{{ route('transactions') }}" class="text-xs text-accent underline underline-offset-2">See all</a>
            </div>
            <ul class="divide-y divide-line">
                @foreach ($recent as $entry)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <span class="flex min-w-0 items-center gap-2.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-canvas text-sm">
                                {{ $entry->category->icon ?: '•' }}
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm">{{ $entry->category->name ?? 'Uncategorised' }}</span>
                                <span class="block truncate text-xs text-ink-faint">
                                    {{ $entry->date->format('M j') }}@if ($entry->description) · {{ $entry->description }}@endif
                                </span>
                            </span>
                        </span>
                        <x-ui.money :amount="$entry->amount" :type="$entry->type" class="shrink-0 text-sm" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-app-layout>
