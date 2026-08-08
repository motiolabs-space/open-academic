@extends('layouts.app')

@section('title', 'Koreksi Nilai')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5">
        <x-alert tone="warning">
            Nilai final adalah catatan resmi yang mungkin sudah tercetak pada transkrip
            dan dilaporkan ke PDDIKTI. Setiap koreksi wajib beralasan, tercatat pada jejak
            audit, dan mengubah IPK mahasiswa yang bersangkutan.
        </x-alert>
    </div>

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field label="Cari Mahasiswa" name="cari" type="search" :value="$filter['cari'] ?? ''"
                placeholder="Nama atau NIM…" class="min-w-[260px] flex-1" required />
            <x-button type="submit">Cari Nilai Final</x-button>
        </form>
    </x-card>

    @if (($filter['cari'] ?? '') === '')
        <x-card>
            <x-empty-state title="Cari mahasiswa lebih dulu"
                description="Koreksi selalu bermula dari satu nama tertentu, bukan dari menelusuri daftar nilai." />
        </x-card>
    @elseif ($hasil->isEmpty())
        <x-card>
            <x-empty-state title="Tidak ada nilai final yang cocok"
                description="Hanya nilai yang sudah difinalisasi dosen yang dapat dikoreksi di sini." />
        </x-card>
    @else
        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                            <th class="px-5 py-3 font-semibold">Mata Kuliah</th>
                            <th class="px-5 py-3 text-center font-semibold">Nilai Saat Ini</th>
                            <th class="px-5 py-3 font-semibold">Koreksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasil as $n)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $n->mahasiswa->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $n->mahasiswa->nim }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    {{ $n->kelasKuliah->mataKuliah->nama }}
                                    <div class="text-[11.5px] text-ink-faint">
                                        {{ $n->kelasKuliah->tahunAkademik->kode }} ·
                                        {{ $n->krsDetail->sks ?? '—' }} SKS
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <div class="tabular font-serif text-[20px] font-semibold">
                                        {{ $n->nilai_huruf?->value ?? '—' }}
                                    </div>
                                    <div class="tabular text-[11.5px] text-ink-faint">
                                        {{ number_format((float) $n->nilai_angka, 1, ',', '.') }}
                                    </div>
                                    @if ($n->catatan_koreksi)
                                        <x-chip tone="warning" class="mt-1">pernah dikoreksi</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <form method="POST" action="{{ route('admin.koreksi-nilai.simpan', $n) }}"
                                        class="flex flex-wrap items-start gap-2"
                                        onsubmit="return confirm('Ubah nilai {{ $n->mahasiswa->nama }}? IPK-nya ikut berubah dan perubahan ini tercatat permanen.');">
                                        @csrf
                                        <input type="number" name="nilai_angka" step="0.01" min="0" max="100" required
                                            value="{{ $n->nilai_angka }}"
                                            class="tabular w-24 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                        <input type="text" name="alasan" required minlength="10"
                                            placeholder="Alasan koreksi (wajib, min. 10 huruf)"
                                            class="min-w-[240px] flex-1 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                        <x-button type="submit" size="sm">Simpan Koreksi</x-button>
                                    </form>

                                    @if ($n->catatan_koreksi)
                                        <p class="mt-1.5 text-[11.5px] italic text-ink-faint">
                                            Koreksi sebelumnya: {{ $n->catatan_koreksi }}
                                        </p>
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
