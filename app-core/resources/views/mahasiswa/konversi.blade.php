@php
    use App\Enums\StatusKonversi;
    use App\Support\Format;
@endphp
@extends('layouts.app')

@section('title', __('swalayan.konversi.judul'))

@section('content')
    @include('mahasiswa._swalayan-pesan')

    <div class="mb-5 grid gap-4 sm:grid-cols-2">
        <x-stat-card :label="__('swalayan.konversi.sks_diakui')" :value="$sudah"
            :meta="__('swalayan.konversi.batas', ['batas' => $batas])" />
        <x-stat-card :label="__('swalayan.konversi.menunggu')"
            :value="$daftar->where('status', StatusKonversi::Diajukan)->count()" />
    </div>

    <x-card flush :title="__('swalayan.konversi.daftar')">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">{{ __('swalayan.konversi.kolom.mata_kuliah') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('swalayan.konversi.kolom.asal') }}</th>
                        <th class="px-5 py-3 text-center font-semibold">{{ __('swalayan.konversi.kolom.sks') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('swalayan.kolom.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $k)
                        <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-konversi="{{ $k->uuid }}">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $k->mataKuliah->kode }} · {{ $k->mataKuliah->nama }}</div>
                                <div class="text-[11px] text-ink-faint">{{ __('swalayan.konversi.sks_kurikulum', ['sks' => $k->mataKuliah->sks]) }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <div>{{ $k->asal_nama }}</div>
                                <div class="text-[11.5px] text-ink-faint">
                                    {{ $k->asal_institusi ?? $k->jenis->label() }}
                                    @if ($k->asal_sks) · {{ $k->asal_sks }} SKS @endif
                                    @if ($k->asal_nilai) · {{ $k->asal_nilai }} @endif
                                </div>
                            </td>
                            <td class="tabular px-5 py-3 text-center">
                                {{-- Hanya yang diakui terhitung; angka usulan bukan janji. --}}
                                @if ($k->status === StatusKonversi::Disetujui)
                                    <span class="font-semibold">{{ $k->sks_diakui }}</span>
                                    <div class="text-[11px] text-ink-faint">{{ $k->nilai_huruf ?? __('swalayan.konversi.tanpa_nilai') }}</div>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <x-chip :tone="$k->status->tone()">{{ $k->status->label() }}</x-chip>
                                @if ($k->diputus_at)
                                    <div class="tabular mt-1 text-[11px] text-ink-faint">{{ Format::tanggal($k->diputus_at) }}</div>
                                @endif
                                @if ($k->catatan)
                                    <p class="mt-1 max-w-xs text-[12px] text-ink-muted">{{ $k->catatan }}</p>
                                @endif

                                @if ($k->dokumen_diminta && $k->status === StatusKonversi::Diajukan)
                                    <div class="mt-2 rounded-card border border-line bg-canvas p-3" data-dokumen-diminta>
                                        <p class="text-[12px]">
                                            <span class="font-semibold">{{ __('swalayan.konversi.diminta', ['tanggal' => Format::tanggal($k->dokumen_diminta_at)]) }}</span>
                                            {{ $k->dokumen_diminta }}
                                        </p>
                                        @if ($k->berkas_mahasiswa_path)
                                            <a href="{{ route('berkas.konversi', [$k, 'mahasiswa']) }}" class="mt-1 block text-[12px] text-navy underline">
                                                {{ __('swalayan.konversi.berkas_mahasiswa', ['tanggal' => Format::tanggal($k->berkas_mahasiswa_at)]) }}
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('mahasiswa.konversi.unggah', $k) }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                                            @csrf
                                            @include('mahasiswa._unggah-berkas', ['nama' => 'berkas', 'label' => __('swalayan.konversi.unggah_label'), 'wajib' => true])
                                            <x-button type="submit" size="sm" variant="outline">
                                                {{ $k->berkas_mahasiswa_path ? __('swalayan.konversi.unggah_ganti') : __('swalayan.konversi.unggah_tombol') }}
                                            </x-button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state :title="__('swalayan.konversi.kosong')" :description="__('swalayan.konversi.kosong_penjelasan')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
