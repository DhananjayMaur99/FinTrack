@props(['title' => 'FinTrack'])
@php
    $navItems = [
        ['label' => 'Overview', 'route' => 'dashboard', 'icon' => 'grid'],
        ['label' => 'Entries', 'route' => 'transactions', 'icon' => 'rows'],
        ['label' => 'Budgets', 'route' => 'budgets', 'icon' => 'gauge'],
        ['label' => 'Reasons', 'route' => 'reasons', 'icon' => 'tag'],
        ['label' => 'Account', 'route' => 'account', 'icon' => 'user'],
    ];

    $icons = [
        'grid' => '<rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="11" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/>',
        'rows' => '<line x1="3" y1="5.5" x2="17" y2="5.5"/><line x1="3" y1="10" x2="17" y2="10"/><line x1="3" y1="14.5" x2="12" y2="14.5"/>',
        'gauge' => '<path d="M3 15a7 7 0 0 1 14 0"/><line x1="10" y1="15" x2="13.2" y2="8.8"/>',
        'tag' => '<path d="M10.4 3H4.5a1.5 1.5 0 0 0-1.5 1.5v5.9a1.5 1.5 0 0 0 .44 1.06l5.6 5.6a1.5 1.5 0 0 0 2.12 0l5.9-5.9a1.5 1.5 0 0 0 0-2.12l-5.6-5.6A1.5 1.5 0 0 0 10.4 3Z"/><circle cx="7" cy="7" r="1.1"/>',
        'user' => '<circle cx="10" cy="7" r="3"/><path d="M4 16.5a6 6 0 0 1 12 0"/>',
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1e5a43">
    <title>{{ $title }} · FinTrack</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|ibm-plex-mono:500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- x-data on the body gives every button a scope to $dispatch from. --}}
<body class="h-full font-sans antialiased" x-data>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 hidden w-60 flex-col border-r border-line bg-surface px-4 py-6 md:flex">
        <a href="{{ route('dashboard') }}" class="mb-7 flex items-center gap-2 px-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-accent text-sm font-semibold text-white">F</span>
            <span class="text-base font-semibold tracking-tight">FinTrack</span>
        </a>

        <button type="button" x-on:click="$dispatch('open-modal', 'quick-entry')" class="btn btn-primary mb-6 w-full">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M10 4v12M4 10h12" />
            </svg>
            Add entry
        </button>

        <nav class="flex flex-col gap-0.5">
            @foreach ($navItems as $item)
                @php($active = request()->routeIs($item['route']))
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors {{ $active ? 'bg-accent-dim font-medium text-accent' : 'text-ink-soft hover:bg-canvas hover:text-ink' }}">
                    <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] !!}</svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="mt-auto px-3">
            @csrf
            <button class="text-sm text-ink-faint hover:text-out">Sign out</button>
        </form>
    </aside>

    <div class="flex min-h-full flex-col md:pl-60">
        {{-- Mobile top bar --}}
        <header class="sticky top-0 z-20 flex items-center justify-between border-b border-line bg-surface px-4 py-3 md:hidden">
            <span class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded bg-accent text-xs font-semibold text-white">F</span>
                <span class="font-semibold tracking-tight">FinTrack</span>
            </span>
            <span class="text-sm text-ink-soft">{{ $title }}</span>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 pb-28 pt-5 md:px-8 md:pb-12 md:pt-8">
            <x-ui.flash />
            {{ $slot }}
        </main>
    </div>

    {{-- Mobile quick add --}}
    <button
        type="button"
        x-on:click="$dispatch('open-modal', 'quick-entry')"
        class="fixed bottom-20 right-4 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-accent text-white md:hidden"
        aria-label="Add entry"
    >
        <svg class="h-6 w-6" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M10 4v12M4 10h12" />
        </svg>
    </button>

    {{-- Mobile bottom tabs --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 flex border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] md:hidden">
        @foreach ($navItems as $item)
            @php($active = request()->routeIs($item['route']))
            <a href="{{ route($item['route']) }}"
               class="flex flex-1 flex-col items-center gap-1 py-2 text-[10px] {{ $active ? 'text-accent' : 'text-ink-faint' }}">
                <svg class="h-[20px] w-[20px]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] !!}</svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    {{-- Global quick-entry modal --}}
    <x-ui.modal name="quick-entry" title="Add entry">
        @if ($quickReasons->isEmpty())
            <p class="text-sm text-ink-soft">
                Add a reason first so you can tag this entry.
            </p>
            <a href="{{ route('reasons') }}" class="btn btn-primary mt-4 w-full">Go to reasons</a>
        @else
            <form method="POST" action="{{ route('transactions.store') }}">
                @csrf
                <div x-data="{ type: 'expense' }" class="mb-4 grid grid-cols-2 gap-2">
                    <input type="hidden" name="type" :value="type">
                    <button type="button" x-on:click="type = 'expense'"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="type === 'expense' ? 'border-out bg-out-dim text-out font-medium' : 'border-line text-ink-soft'">
                        Money out
                    </button>
                    <button type="button" x-on:click="type = 'income'"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="type === 'income' ? 'border-accent bg-accent-dim text-accent font-medium' : 'border-line text-ink-soft'">
                        Money in
                    </button>
                </div>

                <x-ui.field label="Amount" name="amount" type="number" step="0.01" min="0.01" placeholder="0.00" required autofocus />

                <x-ui.field label="Reason" name="category_id" type="select">
                    @foreach ($quickReasons as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->icon ? $reason->icon.' ' : '' }}{{ $reason->name }}</option>
                    @endforeach
                </x-ui.field>

                <x-ui.field label="Date" name="date" type="date" :value="now()->toDateString()" required />
                <x-ui.field label="Note" name="description" placeholder="Optional" />

                <button type="submit" class="btn btn-primary w-full">Save entry</button>
            </form>
        @endif
    </x-ui.modal>
</body>
</html>
