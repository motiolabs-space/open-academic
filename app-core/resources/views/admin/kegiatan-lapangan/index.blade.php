@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('kegiatan_lapangan.judul'))

@section('aksi')
    @can('create', \App\Models\Kemahasiswaan\KegiatanLapangan::class)
        <x-button href="{{ route('admin.kegiatan-lapangan.create') }}" size="sm">+ {{ __('kegiatan_lapangan.aksi.baru') }}</x-button>
    @endcan
@endsection

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
            <x-field :label="__('kegiatan_lapangan.field.tahun_akademik_id')" name="term" :options="$daftarTerm" :value="$termId" />
            <div class="flex items-end"><x-button type="submit">{{ __('kegiatan_lapangan.aksi.tampilkan') }}</x-button></div>
        </form>
    </x-card>

    <x-card flush :title="__('kegiatan_lapangan.kartu.daftar')">
        @if ($daftar->isEmpty())
            <x-empty-state :title="__('kegiatan_lapangan.keterangan.kosong')" :description="__('kegiatan_lapangan.keterangan.kosong_deskripsi')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('kegiatan_lapangan.kolom.kegiatan') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kegiatan_lapangan.kolom.waktu') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.lokasi') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.kelompok') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.peserta') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kegiatan_lapangan.kolom.pendaftaran') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $k)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.kegiatan-lapangan.show', $k) }}" class="font-medium text-navy hover:underline">{{ $k->nama }}</a>
                                    <div class="text-[11.5px] text-ink-faint">{{ $k->jenis->label() }} · {{ $k->tahunAkademik->nama }}</div>
                                </td>
                                <td class="tabular px-5 py-3">{{ Format::tanggal($k->tanggal_mulai) }} – {{ Format::tanggal($k->tanggal_selesai) }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($k->lokasi_count) }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($k->kelompok_count) }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($k->peserta_count) }}</td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$k->pendaftaran_dibuka ? 'success' : 'neutral'">
                                        {{ $k->pendaftaran_dibuka ? __('kegiatan_lapangan.keterangan.dibuka') : __('kegiatan_lapangan.keterangan.ditutup') }}
                                    </x-chip>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $daftar->links() }}</div>
        @endif
    </x-card>
@endsection
