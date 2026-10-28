@extends('layouts.app')

@section('title', $judul)

@php use App\Support\Format; @endphp

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        {{-- Periode --}}
        <div class="flex min-w-0 flex-col gap-5">
            @if ($terdaftar)
                <x-alert tone="success" data-wisuda="terdaftar">
                    {{ __('wisuda.layar.terdaftar_lain', ['periode' => $terdaftar->periode->nama]) }}
                    {{ __('wisuda.layar.terdaftar', ['urut' => $terdaftar->nomor_urut]) }}
                </x-alert>
            @elseif (! $syarat->memenuhi())
                <x-alert tone="warning" data-wisuda="belum-memenuhi">
                    {{ __('wisuda.layar.belum_memenuhi') }}
                </x-alert>
            @endif

            <x-card :title="__('wisuda.layar.periode_judul')" flush>
                @forelse ($periode as $p)
                    @php $milikSaya = $terdaftar?->wisuda_periode_id === $p->id; @endphp
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line/50 px-5 py-4 last:border-b-0"
                        data-periode="{{ $p->uuid }}">
                        <div class="min-w-0">
                            <div class="font-medium text-navy">{{ $p->nama }}</div>
                            <div class="tabular mt-0.5 text-[12px] text-ink-muted">
                                {{ Format::tanggal($p->tanggal) }}@if ($p->lokasi) · {{ $p->lokasi }}@endif
                            </div>
                            <div class="tabular mt-0.5 text-[11.5px] text-ink-faint">
                                @if ($p->kuota)
                                    {{ __('wisuda.layar.kuota', ['sisa' => max(0, $p->kuota - $p->peserta_count), 'kuota' => $p->kuota]) }}
                                @else
                                    {{ __('wisuda.layar.tanpa_kuota') }}
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if ($milikSaya)
                                @if ($terdaftar->nomor_ijazah)
                                    <span class="text-[11.5px] text-ink-faint">{{ __('wisuda.layar.ijazah_terkunci') }}</span>
                                @else
                                    <form method="POST" action="{{ route('mahasiswa.wisuda.batal', $p) }}"
                                        data-confirm="{{ __('wisuda.layar.batal_konfirmasi', ['periode' => $p->nama]) }}">
                                        @csrf @method('DELETE')
                                        <x-button type="submit" variant="outline" size="sm">{{ __('wisuda.layar.batal') }}</x-button>
                                    </form>
                                @endif
                            @elseif ($terdaftar === null)
                                <form method="POST" action="{{ route('mahasiswa.wisuda.daftar', $p) }}">
                                    @csrf
                                    <x-button type="submit" size="sm" :disabled="! $syarat->memenuhi()">
                                        {{ __('wisuda.layar.daftar') }}
                                    </x-button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12">
                        <x-empty-state :title="__('wisuda.layar.tanpa_periode')"
                            :description="__('wisuda.layar.tanpa_periode_keterangan')" />
                    </div>
                @endforelse
            </x-card>

            {{-- A registration in a period that has since closed still shows,
                 with no button: withdrawing now goes through the office. --}}
            @if ($terdaftar && ! $periode->contains('id', $terdaftar->wisuda_periode_id))
                <x-alert tone="info">{{ __('wisuda.layar.batal_ditutup') }}</x-alert>
            @endif
        </div>

        {{-- Syarat --}}
        <x-card :title="__('wisuda.syarat.judul')" flush>
            <ul>
                @foreach ($syarat->rincian as $s)
                    <li class="flex items-start gap-3 border-b border-line/50 px-5 py-3 last:border-b-0"
                        data-syarat="{{ $s['kode'] }}" data-terpenuhi="{{ $s['terpenuhi'] ? 'ya' : 'tidak' }}">
                        <span @class([
                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                            'bg-success-bg text-success' => $s['terpenuhi'],
                            'bg-danger-bg text-danger' => ! $s['terpenuhi'] && $s['wajib'],
                            'bg-warning-bg text-warning' => ! $s['terpenuhi'] && ! $s['wajib'],
                        ]) aria-hidden="true">{{ $s['terpenuhi'] ? '✓' : ($s['wajib'] ? '×' : '!') }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-[13px] font-medium">
                                {{ $s['label'] }}
                                <x-chip :tone="$s['wajib'] ? 'neutral' : 'info'">
                                    {{ $s['wajib'] ? __('wisuda.syarat.wajib') : __('wisuda.syarat.informasi') }}
                                </x-chip>
                            </div>
                            <div class="tabular mt-0.5 text-[12px] text-ink-muted">{{ $s['keterangan'] }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="border-t border-line px-5 py-3 text-[11.5px] leading-relaxed text-ink-muted">
                {{ __('wisuda.syarat.informasi_pin') }}
            </p>
        </x-card>
    </div>
@endsection
