@props(['amount', 'type' => null, 'signed' => true])
@php
    $value = (float) $amount;
    $isIncome = $type === 'income';
    $tone = match (true) {
        $type === null => 'text-ink',
        $isIncome => 'text-accent',
        default => 'text-out',
    };
    $sign = $signed && $type ? ($isIncome ? '+' : '−') : '';
@endphp
<span {{ $attributes->merge(['class' => "tabular $tone"]) }}>{{ $sign }}{{ number_format($value, 2) }}</span>
