@php use App\Support\Format; use App\Enums\TipePertanyaan; @endphp
@extends('layouts.app')

@section('title', __('kuesioner.hasil.judul'))

@section('content')
    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <x-button href="{{ route('admin.kuesioner.show', $kuesioner) }}" variant="outline" size="sm">← {{ __('kuesioner.hasil.kembali') }}</x-button>

        <div class="flex items-center gap-3">
            <span class="tabular text-[12.5px] text-ink-muted">{{ Format::bulat($responden) }} {{ Str::lower(__('kuesioner.ringkasan.responden')) }}</span>
            @if ($terbuka && $responden > 0)
                <x-button href="{{ route('admin.kuesioner.ekspor', $kuesioner) }}" variant="outline" size="sm">{{ __('kuesioner.aksi.ekspor') }}</x-button>
                    <x-button href="{{ route('admin.kuesioner.ekspor', [$kuesioner, 'format' => 'xlsx']) }}" variant="outline" size="sm">{{ __('kuesioner.aksi.ekspor_xlsx') }}</x-button>
            @endif
        </div>
    </div>

    @unless ($terbuka)
        <x-card>
            <x-empty-state :title="__('kuesioner.hasil.tertahan_judul')"
                :description="__('kuesioner.hasil.tertahan', ['responden' => $responden, 'ambang' => $kuesioner->min_responden])" />
        </x-card>
    @else
        @if ($kuesioner->anonim)
            <p class="mb-4 text-[12px] text-ink-muted">{{ __('kuesioner.hasil.anonim_catatan') }}</p>
        @endif

        <div class="space-y-4">
            @foreach ($hasil as $h)
                @php $p = $h['pertanyaan']; @endphp

                <x-card flush>
                    <header class="flex flex-wrap items-baseline justify-between gap-3 border-b border-line px-5 py-3.5">
                        <h2 class="text-[14px] font-semibold">{{ $p->urutan }}. {{ $p->teks }}</h2>
                        <span class="tabular text-xs text-ink-faint">
                            {{ $p->tipe->label() }} · {{ __('kuesioner.hasil.jawaban', ['jumlah' => Format::bulat($h['jumlah'])]) }}
                        </span>
                    </header>

                    <div class="px-5 py-4">
                        @if ($p->tipe === TipePertanyaan::Teks)
                            @forelse (array_slice($h['teks'], 0, $batasTeks) as $teks)
                                <p class="border-b border-line/50 py-2 text-[13px] leading-relaxed last:border-b-0">{{ $teks }}</p>
                            @empty
                                <p class="text-[12.5px] text-ink-faint">{{ __('kuesioner.hasil.teks_kosong') }}</p>
                            @endforelse

                            @if (count($h['teks']) > $batasTeks)
                                <p class="mt-2 text-[12px] text-ink-muted">{{ __('kuesioner.hasil.teks_lebih', ['sisa' => count($h['teks']) - $batasTeks]) }}</p>
                            @endif
                        @else
                            @if ($h['rerata'] !== null)
                                <div class="mb-3 flex items-baseline gap-2">
                                    <span class="tabular font-serif text-[24px] font-semibold leading-none">{{ Format::angka($h['rerata'], 2) }}</span>
                                    <span class="text-[11px] uppercase tracking-[0.06em] text-ink-faint">{{ __('kuesioner.hasil.rerata') }}</span>
                                </div>
                            @endif

                            @php $puncak = max(1, max($h['sebaran'] ?: [0])); @endphp

                            <div class="space-y-1.5">
                                @foreach ($h['sebaran'] as $nilai => $jumlah)
                                    <div class="grid grid-cols-[minmax(0,180px)_1fr_48px] items-center gap-3 text-[12.5px]">
                                        <span class="truncate text-ink-muted">{{ $nilai }}</span>
                                        <div class="h-2.5 rounded-sm bg-line">
                                            <div class="h-2.5 rounded-sm bg-navy" style="width: {{ round($jumlah / $puncak * 100) }}%"></div>
                                        </div>
                                        <span class="tabular text-right">{{ Format::bulat($jumlah) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endunless
@endsection
