@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('kegiatan_lapangan.judul_dosen'))

@section('content')
    @if ($kelompok->isEmpty())
        <x-empty-state :title="__('kegiatan_lapangan.keterangan.dosen_kosong')" :description="__('kegiatan_lapangan.keterangan.dosen_kosong_deskripsi')" />
    @else
        <x-card flush :title="__('kegiatan_lapangan.kartu.kelompok_bimbingan')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('kegiatan_lapangan.kolom.kelompok') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kegiatan_lapangan.kolom.lokasi') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.peserta') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.dinilai') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.logbook_baru') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kelompok as $k)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <a href="{{ route('dosen.kegiatan-lapangan.show', $k) }}" class="font-medium text-navy hover:underline">{{ $k->nama }}</a>
                                    <div class="text-[11.5px] text-ink-faint">{{ $k->kegiatanLapangan->nama }} · {{ $k->kegiatanLapangan->tahunAkademik->nama }}</div>
                                </td>
                                <td class="px-5 py-3">{{ $k->lokasi?->wilayah() ?? '—' }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($k->anggota_count) }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::bulat($k->dinilai_count) }}</td>
                                <td class="tabular px-5 py-3 text-right">
                                    @if (($belumDiperiksa[$k->id] ?? 0) > 0)
                                        <x-chip tone="warning">{{ Format::bulat($belumDiperiksa[$k->id]) }}</x-chip>
                                    @else
                                        0
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
