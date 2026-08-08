{{--
    `value` renders the headline number. Leave it null and put a chip (or any
    other markup) in the default slot when the headline is not a number.
--}}
@props([
    'label',
    'value' => null,
    'meta' => null,
    'trend' => null,
    'feature' => false,
])

@php
    // The featured variant is navy with the guilloché motif — used once per
    // screen for the single number that matters most.
    $shell = $feature
        ? 'relative overflow-hidden rounded-card bg-navy p-[18px] text-canvas'
        : 'rounded-card border border-line bg-surface p-[18px]';
@endphp

<div {{ $attributes->merge(['class' => $shell]) }}>
    @if ($feature)
        <div class="guilloche-gold pointer-events-none absolute inset-0" aria-hidden="true"></div>
    @endif

    <div class="relative">
        <div @class([
            'text-[11px] font-semibold tracking-[0.08em]',
            'text-canvas/60' => $feature,
            'text-ink-muted' => ! $feature,
        ])>{{ Str::upper($label) }}</div>

        @if ($value !== null)
            <div @class([
                'tabular mt-1 font-serif text-[30px] font-semibold leading-none',
                'text-gold' => $feature,
            ])>{{ $value }}</div>
        @endif

        @if (trim($slot) !== '')
            <div class="mt-2">{{ $slot }}</div>
        @endif

        @if ($trend)
            <div class="mt-1.5 text-[11.5px] font-semibold text-success">{{ $trend }}</div>
        @endif

        @if ($meta)
            <div @class([
                'tabular mt-2 text-[11.5px]',
                'text-canvas/65' => $feature,
                'text-ink-faint' => ! $feature,
            ])>{{ $meta }}</div>
        @endif
    </div>
</div>
