@extends('layouts.app')

@section('title', 'KHS & Transkrip')

@php use App\Support\Format; @endphp

@section('aksi')
    <x-button variant="outline" :href="route('mahasiswa.transkrip')">Unduh Transkrip PDF</x-button>
@endsection

@section('content')
    <div class="mb-5 grid gap-3.5 sm:grid-cols-3">
        <x-stat-card feature label="IPK Kumulatif" :value="Format::angka($terakhir?->ipk ?? 0)" />
        <x-stat-card label="SKS Lulus" :value="Format::bulat($terakhir?->sks_kumulatif ?? 0)"
            :meta="'Syarat lulus '.$mahasiswa->prodi->sks_lulus.' SKS'" />
        <x-stat-card label="IPS Terakhir" :value="Format::angka($terakhir?->ips ?? 0)"
            :meta="$terakhir?->tahunAkademik?->nama ?? '—'" />
    </div>

    @forelse ($riwayat->where('is_final', true) as $semester)
        @php $nilai = $nilaiPerTerm[$semester->tahun_akademik_id] ?? collect(); @endphp

        <x-card class="mb-5" flush>
            <x-slot:title>{{ $semester->tahunAkademik->nama }}</x-slot:title>
            <x-slot:meta>Semester {{ $semester->semester_ke }}</x-slot:meta>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-2.5 font-semibold">Kode</th>
                            <th class="px-5 py-2.5 font-semibold">Mata Kuliah</th>
                            <th class="px-5 py-2.5 text-center font-semibold">SKS</th>
                            <th class="px-5 py-2.5 text-center font-semibold">Nilai</th>
                            <th class="px-5 py-2.5 text-center font-semibold">Huruf</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Mutu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($nilai as $baris)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra hover:bg-highlight">
                                <td class="px-5 py-2.5">{{ $baris->kelasKuliah->mataKuliah->kode }}</td>
                                <td class="px-5 py-2.5 font-medium">{{ $baris->kelasKuliah->mataKuliah->nama }}</td>
                                <td class="px-5 py-2.5 text-center">{{ $baris->krsDetail->sks }}</td>
                                <td class="px-5 py-2.5 text-center">{{ Format::angka($baris->nilai_angka) }}</td>
                                <td class="px-5 py-2.5 text-center">
                                    <span @class([
                                        'inline-grid h-7 w-9 place-items-center rounded-md text-xs font-bold',
                                        'bg-navy text-gold' => $baris->nilai_huruf?->value === 'A',
                                        'bg-line/60 text-ink' => $baris->nilai_huruf?->value !== 'A' && $baris->lulus(),
                                        'bg-danger-bg text-danger' => ! $baris->lulus(),
                                    ])>{{ $baris->nilai_huruf?->value }}</span>
                                </td>
                                <td class="px-5 py-2.5 text-right">{{ Format::angka($baris->mutu()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-line bg-highlight/60 font-semibold">
                            <td class="px-5 py-3" colspan="2">Indeks Prestasi Semester</td>
                            <td class="px-5 py-3 text-center">{{ $semester->sks_semester }}</td>
                            <td class="px-5 py-3 text-center" colspan="2">IPS {{ Format::angka($semester->ips) }}</td>
                            <td class="px-5 py-3 text-right">IPK {{ Format::angka($semester->ipk) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
    @empty
        <x-empty-state
            title="Belum ada hasil studi"
            description="Kartu Hasil Studi terbit setelah dosen pengampu memfinalisasi nilai pada akhir semester."
        />
    @endforelse
@endsection
