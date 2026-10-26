@php use App\Support\Format; use App\Enums\JenisUjianKelas; @endphp
@extends('layouts.app')

@section('title', __('jadwal_ujian.judul'))

@section('aksi')
    @if ($perJenis->isNotEmpty())
        <x-button href="{{ route('mahasiswa.kartu-ujian') }}" variant="outline" size="sm">
            {{ __('jadwal_ujian.aksi.kartu') }}
        </x-button>
    @endif
@endsection

@section('content')
    @if ($perJenis->isEmpty())
        <x-empty-state :title="__('jadwal_ujian.keterangan.portal_kosong')" :description="__('jadwal_ujian.keterangan.portal_kosong_deskripsi')" />
    @else
        {{-- UAS di atas: begitu jadwal akhir terbit, itulah yang sedang dicari. --}}
        @foreach (array_reverse(JenisUjianKelas::cases()) as $jenis)
            @continue(!$perJenis->has($jenis->value))
            <x-card flush class="mb-5" :title="$jenis->label()">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.waktu') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.kelas') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.keterangan.ruang_anda') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($perJenis[$jenis->value] as $j)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="tabular px-5 py-3">
                                        <div class="font-medium">{{ Format::tanggalHari($j->tanggal) }}</div>
                                        <div class="text-[11.5px] text-ink-faint">{{ Format::rentangJam($j->jam_mulai, $j->jam_selesai) }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $j->kelasKuliah->mataKuliah->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">{{ $j->kelasKuliah->mataKuliah->kode }} · {{ $j->kelasKuliah->namaLengkap() }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($ruang[$j->id])
                                            <span class="font-medium">{{ $ruang[$j->id]->kode }}</span>
                                            <div class="text-[11.5px] text-ink-faint">{{ $ruang[$j->id]->nama }}</div>
                                        @else
                                            <x-chip tone="warning">{{ __('jadwal_ujian.keterangan.tanpa_kursi') }}</x-chip>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
    @endif
@endsection
