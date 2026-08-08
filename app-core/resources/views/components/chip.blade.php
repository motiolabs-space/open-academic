@props([
    'tone' => 'neutral',
    'dot' => false,
])

@php
    // Semantic colours are deliberately desaturated so a table full of chips
    // still reads as an official record rather than a dashboard.
    $tones = [
        'success' => 'bg-success-bg text-success border-success-line',
        'warning' => 'bg-warning-bg text-warning border-warning-line',
        'danger' => 'bg-danger-bg text-danger border-danger-line',
        'info' => 'bg-info-bg text-info border-info-line',
        'neutral' => 'bg-line/50 text-ink-muted border-line',
        'gold' => 'bg-gold/15 text-warning border-gold/40',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-[5px] text-xs font-semibold '
        .($tones[$tone] ?? $tones['neutral']),
]) }}>
    @if ($dot)
        <span class="text-[8px] leading-none" aria-hidden="true">●</span>
    @endif
    {{ $slot }}
</span>
