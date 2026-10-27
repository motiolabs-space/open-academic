@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('pemanfaatan_ruang.kosong.judul'))

@section('content')
    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-field :label="__('pemanfaatan_ruang.kosong.field.term')" name="term" :options="$daftarTerm" :value="$term->id" required />
            <x-field :label="__('pemanfaatan_ruang.kosong.field.hari')" name="hari" :options="$hariPilihan" :value="request('hari')" />
            <x-field :label="__('pemanfaatan_ruang.kosong.field.tanggal')" name="tanggal" type="date" :value="request('tanggal')" />
            <x-field :label="__('pemanfaatan_ruang.kosong.field.kapasitas_min')" name="kapasitas_min" type="number" :value="request('kapasitas_min')" />
            <x-field :label="__('pemanfaatan_ruang.kosong.field.jam_mulai')" name="jam_mulai" type="time" :value="request('jam_mulai')" />
            <x-field :label="__('pemanfaatan_ruang.kosong.field.jam_selesai')" name="jam_selesai" type="time" :value="request('jam_selesai')" />
            <x-field :label="__('pemanfaatan_ruang.kosong.field.gedung')" name="gedung" :options="$daftarGedung" :value="request('gedung')" />
            <x-field :label="__('pemanfaatan_ruang.kosong.field.jenis')" name="jenis" :options="$jenisPilihan" :value="request('jenis')" />
            <div class="flex items-end sm:col-span-2 lg:col-span-4">
                <x-button type="submit">{{ __('pemanfaatan_ruang.kosong.cari') }}</x-button>
            </div>
        </form>
        <p class="mt-3 text-[12px] text-ink-muted">{{ __('pemanfaatan_ruang.kosong.petunjuk_tanggal') }}</p>
    </x-card>

    @if ($hasil === null)
        <x-card>
            <x-empty-state :title="__('pemanfaatan_ruang.kosong.mulai')" :description="__('pemanfaatan_ruang.kosong.mulai_deskripsi')" />
        </x-card>
    @else
        @php
            $k = $hasil->kriteria;
            $waktu = ($k->tanggal ? Format::tanggalHari($k->tanggal) : ($hariPilihan[$k->hari] ?? '—'))
                .' · '.Format::rentangJam($k->jamMulai, $k->jamSelesai);
        @endphp

        <div class="mb-5 space-y-3">
            <x-alert tone="info">{{ __('pemanfaatan_ruang.kosong.petunjuk') }}</x-alert>
            @if ($hasil->libur)
                <x-alert tone="warning">{{ __('pemanfaatan_ruang.kosong.libur', ['judul' => $hasil->libur]) }}</x-alert>
            @endif
            @if ($hasil->diLuarOperasional)
                <x-alert tone="warning">
                    {{ __('pemanfaatan_ruang.kosong.di_luar', [
                        'hari' => Format::daftarHari((array) config('ruang.operasional.hari')),
                        'jam' => Format::rentangJam((string) config('ruang.operasional.jam_mulai'), (string) config('ruang.operasional.jam_selesai')),
                    ]) }}
                </x-alert>
            @endif
        </div>

        <x-card flush class="mb-5" :title="__('pemanfaatan_ruang.kosong.tersedia').' ('.Format::bulat($hasil->kosong->count()).')'"
            :meta="__('pemanfaatan_ruang.kosong.ringkasan', ['waktu' => $waktu, 'kapasitas' => Format::bulat($k->kapasitasMinimum)])">
            @if ($hasil->kosong->isEmpty())
                <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('pemanfaatan_ruang.kosong.tanpa_hasil') }}</p>
            @else
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.ruang') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.gedung') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.jenis') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.kapasitas') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasil->kosong as $r)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <span class="tabular font-medium">{{ $r->kode }}</span>
                                    <span class="text-ink-faint">· {{ $r->nama }}</span>
                                </td>
                                <td class="px-5 py-3 text-[12.5px]">{{ $r->gedung?->nama ?? '—' }}</td>
                                <td class="px-5 py-3 text-[12.5px]">{{ $jenisPilihan[$r->jenis] ?? $r->jenis }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($r->kapasitas) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        @if ($hasil->terpakai->isNotEmpty())
            <x-card flush :title="__('pemanfaatan_ruang.kosong.terpakai').' ('.Format::bulat($hasil->terpakai->count()).')'">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.ruang') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.kapasitas') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.dipakai_oleh') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasil->terpakai as $baris)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-5 py-3 font-medium">{{ $baris['ruang']->kode }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($baris['ruang']->kapasitas) }}</td>
                                <td class="px-5 py-3 text-[12.5px]">
                                    @foreach ($baris['oleh'] as $oleh)
                                        <div class="tabular">{{ $oleh }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        @endif
    @endif
@endsection
