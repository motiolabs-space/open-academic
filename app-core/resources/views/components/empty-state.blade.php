@props([
    'title',
    'description' => null,
])

<div class="guilloche-navy relative rounded-card border border-dashed border-line-input px-6 py-12 text-center">
    <h3 class="font-serif text-lg font-semibold">{{ $title }}</h3>

    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-[13px] leading-relaxed text-ink-muted">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5 flex justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
