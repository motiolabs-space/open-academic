@extends('layouts.app')

@section('title', 'Data Mahasiswa')

@php use App\Support\Format; @endphp

@section('content')
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="min-w-[220px] flex-1">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Cari</span>
                <input
                    type="search"
                    name="cari"
                    value="{{ $filter['cari'] ?? '' }}"
                    placeholder="Nama atau NIM…"
                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10"
                >
            </label>

            <label>
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Program Studi</span>
                <select name="prodi" class="rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                    <option value="">Semua</option>
                    @foreach ($daftarProdi as $prodi)
                        <option value="{{ $prodi->id }}" @selected(($filter['prodi'] ?? '') == $prodi->id)>
                            {{ $prodi->namaLengkap() }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Status</span>
                <select name="status" class="rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                    <option value="">Semua</option>
                    @foreach ($daftarStatus as $kode => $label)
                        <option value="{{ $kode }}" @selected(($filter['status'] ?? '') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <x-button type="submit">Terapkan</x-button>

            @if (array_filter($filter))
                <x-button variant="ghost" :href="route('admin.mahasiswa')">Reset</x-button>
            @endif
        </form>
    </x-card>

    <x-card flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">NIM</th>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-5 py-3 font-semibold">Program Studi</th>
                        <th class="px-5 py-3 text-center font-semibold">Angkatan</th>
                        <th class="px-5 py-3 font-semibold">Dosen Wali</th>
                        <th class="px-5 py-3 text-center font-semibold">Status</th>
                        <th class="px-5 py-3 text-center font-semibold">Siap PDDIKTI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mahasiswa as $baris)
                        @php
                            // Neo Feeder rejects a biodata push without these.
                            $siapFeeder = filled($baris->nik) && $baris->tanggal_lahir && filled($baris->tempat_lahir);
                        @endphp

                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra hover:bg-highlight">
                            <td class="tabular px-5 py-3">{{ $baris->nim }}</td>
                            <td class="px-5 py-3 font-medium">{{ $baris->nama }}</td>
                            <td class="px-5 py-3 text-ink-muted">{{ $baris->prodi->namaLengkap() }}</td>
                            <td class="tabular px-5 py-3 text-center">{{ $baris->angkatan }}</td>
                            <td class="px-5 py-3 text-ink-muted">{{ $baris->dosenWali?->nama ?? '—' }}</td>
                            <td class="px-5 py-3 text-center">
                                <x-chip :tone="$baris->status->tone()" dot>{{ $baris->status->label() }}</x-chip>
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if ($siapFeeder)
                                    <x-chip tone="success">Lengkap</x-chip>
                                @else
                                    <x-chip tone="danger" title="NIK atau data kelahiran belum lengkap">Perlu dilengkapi</x-chip>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12">
                                <x-empty-state
                                    title="Tidak ada mahasiswa yang cocok"
                                    description="Ubah kata kunci atau bersihkan filter untuk melihat seluruh data."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($mahasiswa->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $mahasiswa->links() }}</div>
        @endif
    </x-card>
@endsection
