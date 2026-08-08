@props([
    'title',
    'context' => null,
    'breadcrumb' => [],
])

<div class="mb-6">
    @if ($breadcrumb)
        <nav class="mb-2.5 text-xs text-ink-faint">
            @foreach ($breadcrumb as $label => $href)
                @if (is_int($label))
                    <span class="font-semibold text-ink">{{ $href }}</span>
                @else
                    <a href="{{ $href }}" class="hover:text-gold">{{ $label }}</a>
                    <span class="px-1" aria-hidden="true">/</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-serif text-[28px] font-semibold leading-tight">{{ $title }}</h1>
            @if ($context)
                <p class="mt-1.5 text-[13px] text-ink-muted">{{ $context }}</p>
            @endif
        </div>

        @if (trim($slot) !== '')
            <div class="flex items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</div>
