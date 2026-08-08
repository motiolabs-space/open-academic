@props([
    'title' => null,
    'meta' => null,
    'flush' => false,
])

{{-- Cards carry a border, not a shadow. Only overlays are allowed to lift. --}}
<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-card border border-line bg-surface']) }}>
    @if ($title)
        <header class="flex items-baseline justify-between gap-3 border-b border-line px-5 py-3.5">
            <h2 class="text-[14.5px] font-semibold">{{ $title }}</h2>
            @if ($meta)
                <span class="text-xs text-ink-faint">{{ $meta }}</span>
            @endif
        </header>
    @endif

    <div @class(['px-5 py-4' => ! $flush])>
        {{ $slot }}
    </div>
</section>
