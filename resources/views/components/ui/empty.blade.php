@props(['title', 'description' => null])
<div class="px-5 py-10 text-center">
    <p class="text-sm font-medium text-ink">{{ $title }}</p>
    @if ($description)
        <p class="mx-auto mt-1 max-w-xs text-sm text-ink-soft">{{ $description }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-4 flex justify-center">{{ $slot }}</div>
    @endif
</div>
