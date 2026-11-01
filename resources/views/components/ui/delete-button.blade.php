@props(['action', 'label' => 'Delete'])
<div x-data="{ confirming: false }" class="flex items-center gap-2">
    <button
        type="button"
        x-show="!confirming"
        x-on:click="confirming = true"
        class="text-xs text-ink-faint hover:text-out"
    >{{ $label }}</button>

    <form method="POST" action="{{ $action }}" x-show="confirming" x-cloak class="flex items-center gap-2">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-xs font-medium text-out">Confirm</button>
        <button type="button" x-on:click="confirming = false" class="text-xs text-ink-faint">Cancel</button>
    </form>
</div>
