@if (session('status'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 4000)"
        class="mb-5 flex items-center justify-between gap-3 rounded-lg border border-accent/30 bg-accent-dim px-4 py-2.5 text-sm text-ink"
    >
        <span>{{ session('status') }}</span>
        <button type="button" x-on:click="show = false" class="text-ink-faint hover:text-ink" aria-label="Dismiss">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <path d="M5 5l10 10M15 5L5 15" />
            </svg>
        </button>
    </div>
@endif
