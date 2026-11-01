@props(['name', 'title' => null, 'open' => false])
<div
    x-data="{ open: @js($open) }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
        <div class="absolute inset-0 bg-ink/40" x-on:click="open = false" aria-hidden="true"></div>

        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-t-xl border border-line bg-surface sm:rounded-xl"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button type="button" x-on:click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                        <path d="M5 5l10 10M15 5L5 15" />
                    </svg>
                </button>
            </div>
            <div class="px-5 py-4">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
