@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'hint' => null])
<div {{ $attributes->only('class')->merge(['class' => 'mb-4']) }}>
    @if ($label)
        <label class="label" for="{{ $name }}">{{ $label }}</label>
    @endif

    @if ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'input']) }}>
            {{ $slot }}
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="2" {{ $attributes->except('class')->merge(['class' => 'input']) }}>{{ old($name, $value) }}</textarea>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            {{ $attributes->except('class')->merge(['class' => 'input']) }}
        />
    @endif

    @if ($hint)
        <p class="mt-1 text-xs text-ink-faint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-out">{{ $message }}</p>
    @enderror
</div>
