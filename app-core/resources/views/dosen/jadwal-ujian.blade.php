@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('jadwal_ujian.judul'))

@section('content')
    @if ($mengawasi->isEmpty() && $mengampu->isEmpty())
        <x-empty-state :title="__('jadwal_ujian.keterangan.portal_kosong')" :description="__('jadwal_ujian.keterangan.portal_kosong_deskripsi')" />
    @else
        {{-- Yang diawasi lebih dulu: di situ dosen diharapkan berdiri di ruang
             tertentu pada jam tertentu. --}}
        @foreach (['mengawasi' => $mengawasi, 'mengampu' => $mengampu] as $kunci => $daftar)
            @continue($daftar->isEmpty())
            <x-card flush class="mb-5" :title="__('jadwal_ujian.kartu.'.$kunci)">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.waktu') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.kelas') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.ruang') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.pengawas') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $j)
                                @php $tugas = $j->pengawas->firstWhere('dosen_id', $dosen->id); @endphp
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="tabular px-5 py-3">
                                        <div class="font-medium">{{ Format::tanggalHari($j->tanggal) }}</div>
                                        <div class="text-[11.5px] text-ink-faint">{{ Format::rentangJam($j->jam_mulai, $j->jam_selesai) }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-chip>{{ $j->jenis->singkatan() }}</x-chip>
                                        <span class="font-medium">{{ $j->kelasKuliah->mataKuliah->nama }}</span>
                                        <div class="tabular text-[11.5px] text-ink-faint">{{ $j->kelasKuliah->namaLengkap() }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3">
                                        @if ($kunci === 'mengawasi' && $tugas?->jadwalUjianRuang)
                                            <span class="font-medium">{{ $tugas->jadwalUjianRuang->ruang?->kode }}</span>
                                        @else
                                            {{ $j->ruang->map(fn ($r) => $r->ruang?->kode)->filter()->implode(', ') ?: '—' }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-[12.5px]">{{ $j->pengawas->map->namaOrang()->implode(', ') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
    @endif
@endsection
