@extends('layouts.app')

@section('title', 'Tugas Akhir')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($statusPilihan as $nilai => $label)
            @if (($rekap[$nilai] ?? 0) > 0)
                <x-stat-card :label="$label" :value="$rekap[$nilai]" />
            @endif
        @endforeach
    </div>

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-field label="Cari" name="cari" :value="$filter['cari'] ?? null"
                placeholder="Judul, nama, atau NIM" />
            <x-field label="Status" name="status" :options="$statusPilihan" :value="$filter['status'] ?? null" />
            <x-field label="Program Studi" name="prodi"
                :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->jenjang->label().' '.$p->nama])"
                :value="$filter['prodi'] ?? null" />
            <div class="flex items-end gap-2">
                <x-button type="submit">Terapkan</x-button>
                <x-button href="{{ route('admin.tugas-akhir') }}" variant="outline">Reset</x-button>
            </div>
        </form>
    </x-card>

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                        <th class="px-5 py-3 font-semibold">Judul</th>
                        <th class="px-5 py-3 font-semibold">Pembimbing</th>
                        <th class="px-5 py-3 text-center font-semibold">Bimbingan</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($daftar as $ta)
                        <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $ta->mahasiswa->nama }}</div>
                                <div class="tabular text-[11.5px] text-ink-faint">{{ $ta->mahasiswa->nim }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="max-w-sm">{{ $ta->judul }}</p>
                                @if ($ta->terlambat())
                                    <x-chip tone="danger" class="mt-1">
                                        Lewat batas {{ $ta->batas_selesai->translatedFormat('j M Y') }}
                                    </x-chip>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @forelse ($ta->pembimbing as $p)
                                    <div class="text-[12px]">{{ $p->dosen->namaLengkap() }}</div>
                                @empty
                                    {{-- Judul disetujui tetapi belum ada yang membimbing: keadaan
                                         yang tidak pernah menimbulkan keluhan sampai satu semester
                                         terbuang. Ditandai, bukan disembunyikan. --}}
                                    <x-chip tone="warning">Belum ada pembimbing</x-chip>
                                @endforelse
                            </td>
                            <td class="tabular px-5 py-3 text-center">{{ $ta->bimbingan_disetujui_count }}</td>
                            <td class="px-5 py-3">
                                <x-chip :tone="$ta->status->tone()">{{ $ta->status->label() }}</x-chip>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-button href="{{ route('admin.tugas-akhir.show', $ta) }}" variant="outline" size="sm">
                                    Kelola
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state
                                    title="Belum ada tugas akhir"
                                    description="Judul yang diajukan mahasiswa akan muncul di sini untuk diputuskan." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
