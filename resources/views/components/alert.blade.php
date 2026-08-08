@props([
    'tone' => 'info',
    'icon' => '!',
])

@php
    $tones = [
        'success' => 'bg-success-bg border-success-line text-success',
        'warning' => 'bg-warning-bg border-warning-line text-warning',
        'danger' => 'bg-danger-bg border-danger-line text-danger',
        'info' => 'bg-info-bg border-info-line text-info',
    ];
@endphp

<div {{ $attributes->merge([
    'class' => 'flex flex-wrap items-center gap-3 rounded-[10px] border px-[18px] py-3.5 text-[13px] '
        .($tones[$tone] ?? $tones['info']),
]) }}>
    <span class="font-bold" aria-hidden="true">{{ $icon }}</span>
    <div class="flex-1 text-ink">{{ $slot }}</div>

    @isset($action)
        <div class="ml-auto">{{ $action }}</div>
    @endisset
</div>
