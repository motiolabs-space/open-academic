@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-control font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50';

    // "sm" exists for actions inside a table row, where a full-size button
    // would set the row height and make a long list unreadable. It stays at
    // 32px tall so it remains a comfortable touch target on mobile.
    $sizes = [
        'sm' => 'px-3 py-1.5 text-[12px] min-h-[32px]',
        'md' => 'px-5 py-2.5 text-[13.5px]',
    ];

    $base .= ' '.($sizes[$size] ?? $sizes['md']);

    $variants = [
        'primary' => 'bg-navy text-canvas hover:bg-navy-hover',
        'gold' => 'bg-gold font-bold text-navy hover:bg-gold-hover',
        'outline' => 'border border-line-input bg-surface text-navy hover:border-navy',

        /*
         * Varian outline untuk permukaan GELAP (hero navy, empty state navy).
         *
         * Ada sebagai varian, bukan diserahkan ke pemanggil lewat class, karena
         * `text-canvas` yang dikirim dari luar KALAH oleh `text-navy` milik
         * varian outline: yang menentukan urutan di stylesheet, bukan urutan di
         * atribut. Landing page sempat menampilkan tombol navy di atas hero navy
         * — teksnya ada, warnanya sama persis dengan latarnya.
         */
        'outline-gelap' => 'border border-canvas/30 bg-transparent text-canvas hover:border-gold',
        'ghost' => 'text-navy hover:bg-line/60',
        'danger' => 'border border-danger-line bg-surface text-danger hover:bg-danger-bg',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
