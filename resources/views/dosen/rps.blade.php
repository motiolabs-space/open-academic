@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'RPS & Jurnal Perkuliahan')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="space-y-5">
        @forelse ($daftar as $baris)
            @php
                $kelas = $baris['kelas'];
                $rps = $baris['rps'];
                $kt = $baris['keterlaksanaan'];
            @endphp

            <x-card :title="$kelas->mataKuliah->nama.' · Kelas '.$kelas->nama"
                :meta="$kelas->mataKuliah->kode">
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-stat-card label="RPS"
                        :value="$rps === null ? 'Belum ada' : 'v'.$rps->versi"
                        :meta="$rps?->status->label() ?? 'perlu disusun'" />
                    <x-stat-card label="Terlaksana"
                        :value="$kt['terlaksana'].'/'.$kt['rencana']"
                        :meta="Format::angka($kt['persen_terlaksana'], 0).'%'" />
                    <x-stat-card label="Berjurnal"
                        :value="$kt['berjurnal'].'/'.$kt['rencana']"
                        :meta="Format::angka($kt['persen_berjurnal'], 0).'%'"
                        :feature="$kt['terlaksana'] - $kt['berjurnal'] >= 2" />
                </div>

                @if ($kt['terlaksana'] - $kt['berjurnal'] >= 2)
                    {{-- Dua angka, dan jaraknya itulah temuannya: kelas ini bukan
                         mengajar lebih sedikit, melainkan mendokumentasikan lebih
                         sedikit. --}}
                    <p class="mt-3 text-[12.5px] leading-relaxed text-warning-ink">
                        {{ $kt['terlaksana'] - $kt['berjurnal'] }} pertemuan sudah terlaksana
                        tetapi belum berjurnal.
                    </p>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button href="{{ route('dosen.rps.susun', $kelas->mataKuliah) }}"
                        :variant="$rps === null ? 'primary' : 'outline'" size="sm">
                        {{ $rps === null ? 'Susun RPS' : 'Revisi RPS' }}
                    </x-button>

                    <x-button href="{{ route('dosen.rps.jurnal', $kelas) }}" variant="outline" size="sm">
                        Isi jurnal
                    </x-button>

                    <x-button href="{{ route('dosen.analitik.kelas', $kelas) }}" variant="ghost" size="sm">
                        Analitik
                    </x-button>

                    {{-- Dua lembar yang dibawa ke ruangan. Kolom tanda tangannya
                         sengaja kosong: tanda tangan basah itu buktinya. --}}
                    <x-button href="{{ route('dosen.kelas.absensi', $kelas) }}" variant="ghost" size="sm">
                        Cetak absensi
                    </x-button>

                    <x-button href="{{ route('dosen.kelas.jurnal', $kelas) }}" variant="ghost" size="sm">
                        Cetak jurnal
                    </x-button>
                </div>
            </x-card>
        @empty
            <x-card>
                <x-empty-state title="Tidak ada kelas pada semester ini"
                    description="RPS disusun per mata kuliah yang Anda ampu." />
            </x-card>
        @endforelse
    </div>
@endsection
