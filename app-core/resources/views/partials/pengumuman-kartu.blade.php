{{-- The announcement card on all three dashboards. The list comes from
     Pengumuman::tampilUntuk(), so the card and the list page never disagree. --}}
@php use App\Support\Format; @endphp

<x-card :title="__('pengumuman.kartu')" flush>
    @forelse ($pengumuman as $item)
        <a href="{{ route($ruteTampil, $item) }}" class="block border-b border-line/60 px-5 py-3.5 last:border-b-0 hover:bg-highlight"
            data-pengumuman="{{ $item->uuid }}">
            <div class="text-[13px] font-semibold leading-snug">
                @if ($item->is_pinned)
                    <span class="text-gold" aria-hidden="true">◆</span>
                    <span class="sr-only">{{ __('pengumuman.disematkan') }}</span>
                @endif
                {{ $item->judul }}
            </div>
            <div class="tabular mt-1 text-[11.5px] text-ink-faint">
                {{ Format::tanggal($item->published_at) }}
            </div>
            @if ($item->ringkasan)
                <p class="mt-1.5 text-xs leading-relaxed text-ink-muted">{{ $item->ringkasan }}</p>
            @endif
        </a>
    @empty
        <div class="px-5 py-8 text-center text-[13px] text-ink-faint">{{ __('pengumuman.kosong') }}</div>
    @endforelse

    @if ($pengumuman->isNotEmpty())
        <div class="border-t border-line px-5 py-2.5 text-right">
            <a href="{{ route($ruteDaftar) }}" class="text-[12px] font-semibold text-navy hover:underline">{{ __('pengumuman.lihat_semua') }}</a>
        </div>
    @endif
</x-card>
