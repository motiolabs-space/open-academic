@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('kuesioner.isi.judul'))

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($daftar->isEmpty())
        <x-card>
            <x-empty-state :title="__('kuesioner.isi.kosong')" :description="__('kuesioner.isi.kosong_deskripsi')" />
        </x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($daftar as $baris)
                @php $k = $baris['kuesioner']; @endphp

                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-[15px] font-semibold">{{ $k->nama }}</h2>
                        <x-chip :tone="$baris['sudah'] ? 'success' : 'warning'" dot>
                            {{ $baris['sudah'] ? __('kuesioner.isi.sudah') : __('kuesioner.isi.belum') }}
                        </x-chip>
                    </div>

                    @if ($k->deskripsi)
                        <p class="mt-1.5 text-[12.5px] leading-relaxed text-ink-muted">{{ $k->deskripsi }}</p>
                    @endif

                    <div class="tabular mt-2 text-[11.5px] text-ink-faint">
                        {{ __('kuesioner.isi.konteks_form', ['jumlah' => $k->pertanyaan_count]) }}
                        · {{ $k->anonim ? __('kuesioner.daftar.anonim') : __('kuesioner.daftar.beridentitas') }}
                        @if ($k->selesai) · {{ __('kuesioner.isi.sampai', ['tanggal' => Format::tanggal($k->selesai)]) }} @endif
                    </div>

                    @unless ($baris['sudah'])
                        <div class="mt-3">
                            <x-button href="{{ route($rute.'.show', $k) }}" size="sm">{{ __('kuesioner.isi.isi') }}</x-button>
                        </div>
                    @endunless
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
