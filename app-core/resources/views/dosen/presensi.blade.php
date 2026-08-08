@extends('layouts.app')

@section('title', 'Presensi')

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($daftar as $kelas)
            @php
                $terlaksana = $kelas->pertemuan->where('is_terlaksana', true)->count();
                $total = max(1, $kelas->pertemuan->count() ?: (int) config('academic.attendance.meetings_per_term'));
            @endphp

            <x-card flush>
                <div class="px-5 py-4">
                    <div class="tabular text-[11.5px] font-semibold text-ink-faint">
                        {{ $kelas->mataKuliah->kode }}
                    </div>
                    <h2 class="mt-0.5 text-[14.5px] font-semibold">{{ $kelas->mataKuliah->nama }}</h2>
                    <div class="tabular mt-1 text-xs text-ink-muted">
                        Kelas {{ $kelas->kode }} · {{ $kelas->jumlah_peserta }} mahasiswa
                    </div>

                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-line">
                        <div class="h-full rounded-full bg-navy" style="width: {{ round($terlaksana / $total * 100) }}%"></div>
                    </div>
                    <div class="tabular mt-1.5 text-[11.5px] text-ink-faint">
                        {{ $terlaksana }} dari {{ $kelas->pertemuan->count() ?: config('academic.attendance.meetings_per_term') }} pertemuan terlaksana
                    </div>
                </div>

                <div class="border-t border-line px-5 py-3">
                    <x-button variant="outline" :href="route('dosen.presensi.kelas', $kelas)" class="w-full px-4 py-2 text-xs">
                        Buka Grid Presensi
                    </x-button>
                </div>
            </x-card>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-empty-state
                    title="Belum ada kelas diampu"
                    description="Presensi tersedia setelah penugasan mengajar ditetapkan."
                />
            </div>
        @endforelse
    </div>
@endsection
