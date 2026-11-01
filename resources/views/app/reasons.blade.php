@php
    $emojiChoices = ['🛒','🍜','🚌','🏠','💡','💊','🛍️','🎬','📺','💰','✈️','🎁','📚','🐾','☕','⛽','🏋️','🧾'];
@endphp
<x-app-layout title="Reasons">
    <div class="mb-1 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold tracking-tight">Reasons</h1>
        <button type="button" x-on:click="$dispatch('open-modal', 'add-reason')" class="btn btn-primary">
            Add reason
        </button>
    </div>
    <p class="mb-5 text-sm text-ink-soft">Why money moved. Every entry gets one, and the totals below cover {{ $monthLabel }}.</p>

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-out/30 bg-out-dim px-4 py-3 text-sm text-out">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($reasons->isEmpty())
        <div class="sheet">
            <x-ui.empty title="No reasons yet" description="Reasons are the labels you tag each entry with — Groceries, Rent, Salary.">
                <button type="button" x-on:click="$dispatch('open-modal', 'add-reason')" class="btn btn-primary">Add your first reason</button>
            </x-ui.empty>
        </div>
    @else
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($reasons as $row)
                @php($reason = $row['reason'])
                <li class="sheet flex items-start justify-between gap-3 px-4 py-3.5">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-canvas text-lg">
                            {{ $reason->icon ?: '•' }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $reason->name }}</p>
                            @if ($row['entries'] > 0)
                                <p class="mt-0.5 text-xs text-ink-faint">
                                    {{ $row['entries'] }} {{ Str::plural('entry', $row['entries']) }}
                                </p>
                                <p class="mt-1 text-sm">
                                    @if ($row['out'] > 0)
                                        <x-ui.money :amount="$row['out']" type="expense" class="text-sm" />
                                    @endif
                                    @if ($row['in'] > 0)
                                        <x-ui.money :amount="$row['in']" type="income" class="text-sm" />
                                    @endif
                                </p>
                            @else
                                <p class="mt-0.5 text-xs text-ink-faint">Nothing this month</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <button
                            type="button"
                            class="text-xs text-ink-faint hover:text-ink"
                            x-on:click="$dispatch('edit-reason', @js(['id' => $reason->id, 'name' => $reason->name, 'icon' => $reason->icon ?? '']))"
                        >Edit</button>
                        <x-ui.delete-button :action="route('reasons.destroy', $reason)" />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Add --}}
    <x-ui.modal name="add-reason" title="New reason">
        <form method="POST" action="{{ route('reasons.store') }}" x-data="{ icon: '🛒' }">
            @csrf
            <x-ui.field label="Name" name="name" placeholder="Groceries" required />

            <p class="label">Symbol</p>
            <input type="hidden" name="icon" :value="icon">
            <div class="mb-4 flex flex-wrap gap-1.5">
                @foreach ($emojiChoices as $emoji)
                    <button type="button" x-on:click="icon = '{{ $emoji }}'"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border text-base"
                            :class="icon === '{{ $emoji }}' ? 'border-accent bg-accent-dim' : 'border-line'">
                        {{ $emoji }}
                    </button>
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary w-full">Add reason</button>
        </form>
    </x-ui.modal>

    {{-- Edit --}}
    <div
        x-data="{ open: false, reason: { id: null, name: '', icon: '' } }"
        x-on:edit-reason.window="reason = { ...$event.detail }; open = true"
        x-on:keydown.escape.window="open = false"
    >
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-ink/40" x-on:click="open = false"></div>
            <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-t-xl border border-line bg-surface sm:rounded-xl">
                <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                    <h2 class="text-base font-semibold">Edit reason</h2>
                    <button type="button" x-on:click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('reasons') }}/' + reason.id" class="px-5 py-4">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="label" for="edit-reason-name">Name</label>
                        <input id="edit-reason-name" class="input" name="name" x-model="reason.name" required>
                    </div>

                    <p class="label">Symbol</p>
                    <input type="hidden" name="icon" :value="reason.icon">
                    <div class="mb-4 flex flex-wrap gap-1.5">
                        @foreach ($emojiChoices as $emoji)
                            <button type="button" x-on:click="reason.icon = '{{ $emoji }}'"
                                    class="flex h-9 w-9 items-center justify-center rounded-lg border text-base"
                                    :class="reason.icon === '{{ $emoji }}' ? 'border-accent bg-accent-dim' : 'border-line'">
                                {{ $emoji }}
                            </button>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Save changes</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
