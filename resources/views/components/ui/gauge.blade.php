@props(['percent' => 0, 'over' => false])
@php
    $pct = max(0, min((float) $percent, 100));
    $tone = $over ? 'bg-out' : ((float) $percent >= 80 ? 'bg-brass' : 'bg-accent');
@endphp
<div class="gauge-track">
    <div class="gauge-fill {{ $tone }}" style="width: {{ $pct }}%"></div>
</div>
