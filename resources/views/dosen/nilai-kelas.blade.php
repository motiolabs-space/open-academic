@extends('layouts.app')

@section('title', 'Input Nilai')

@php
    use App\Support\Format;

    // Skala huruf dikirim ke browser supaya kolom huruf ikut berubah saat
    // dosen mengetik. Nilai yang disimpan tetap dihitung ulang di server.
    $skala = collect(config('academic.grading.scale'))
        ->map(fn (array $b): array => ['letter' => $b['letter'], 'min' => (float) $b['min_score']])
        ->values();

    $bobot = $komponen->mapWithKeys(fn ($k): array => [$k->id => $k->bobot]);
@endphp

@section('aksi')
    @if ($kelas->status_nilai !== 'final' && $periodeDibuka)
        <form
            method="POST"
            action="{{ route('dosen.nilai.finalisasi', $kelas) }}"
            onsubmit="return confirm('Kunci nilai kelas ini? Setelah final, perubahan hanya dapat dilakukan lewat jalur koreksi ter-audit.')"
        >
            @csrf
            <x-button variant="gold" type="submit">Kunci &amp; Finalisasi</x-button>
        </form>
    @endif
@endsection

@section('content')
    @if ($kelas->status_nilai === 'final')
        <x-alert tone="success" class="mb-5">
            <strong>Nilai kelas ini sudah difinalisasi</strong>
            pada {{ Format::tanggal($kelas->finalized_at) }}.
            Perubahan hanya dapat dilakukan melalui jalur koreksi ter-audit oleh bagian akademik.
        </x-alert>
    @elseif (! $periodeDibuka)
        <x-alert tone="warning" class="mb-5">
            Periode pengisian nilai sedang tertutup; perubahan tidak dapat disimpan.
        </x-alert>
    @endif

    @php $rawan = $lembar->filter(fn ($b) => ! $b->layakUas)->count(); @endphp

    @if ($rawan > 0)
        <x-alert tone="danger" class="mb-5">
            <strong>{{ $rawan }} mahasiswa di bawah kehadiran minimum
            {{ (int) config('academic.attendance.min_percent_for_final_exam') }}%.</strong>
            Sistem tidak mengubah nilainya secara otomatis — penetapan kelayakan UAS
            tetap keputusan dosen pengampu dan program studi.
        </x-alert>
    @endif

    <form
        method="POST"
        action="{{ route('dosen.nilai.simpan', $kelas) }}"
        x-data="lembarNilai(@js($bobot), @js($skala))"
    >
        @csrf

        <x-card flush>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-4 py-3 font-semibold">NIM</th>
                            <th class="px-4 py-3 font-semibold">Nama</th>
                            <th class="px-3 py-3 text-center font-semibold">Hadir</th>

                            @foreach ($komponen as $k)
                                <th class="px-3 py-3 text-center font-semibold">
                                    {{ $k->nama }}
                                    <span class="block text-[10px] font-normal normal-case text-ink-faint">
                                        {{ $k->bobot }}%
                                    </span>
                                </th>
                            @endforeach

                            <th class="px-3 py-3 text-center font-semibold">Akhir</th>
                            <th class="px-3 py-3 text-center font-semibold">Huruf</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($lembar as $baris)
                            <tr
                                class="border-b border-line/50 last:border-b-0 odd:bg-zebra"
                                x-data="{ id: {{ $baris->krsDetailId }} }"
                                x-init="mulai(id, @js($baris->komponen))"
                            >
                                <td class="tabular px-4 py-2">{{ $baris->nim }}</td>
                                <td class="px-4 py-2 font-medium">{{ $baris->nama }}</td>

                                <td class="tabular px-3 py-2 text-center">
                                    @if ($baris->persenKehadiran === null)
                                        <span class="text-ink-faint">—</span>
                                    @else
                                        <span class="{{ $baris->layakUas ? 'text-ink-muted' : 'font-semibold text-danger' }}">
                                            {{ Format::angka($baris->persenKehadiran, 0) }}%
                                        </span>
                                    @endif
                                </td>

                                @foreach ($komponen as $k)
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            type="number"
                                            name="nilai[{{ $baris->krsDetailId }}][{{ $k->id }}]"
                                            value="{{ $baris->komponen[$k->id] }}"
                                            min="0" max="100" step="0.01"
                                            @disabled($baris->final || ! $periodeDibuka)
                                            @input="ubah(id, {{ $k->id }}, $event.target.value)"
                                            class="tabular w-20 rounded-control border border-line-input bg-surface px-2 py-1.5 text-center outline-none focus:border-navy focus:ring-4 focus:ring-navy/10 disabled:bg-line/40 disabled:text-ink-faint"
                                        >
                                    </td>
                                @endforeach

                                <td class="tabular px-3 py-2 text-center font-semibold" x-text="akhirTeks(id)"></td>

                                <td class="px-3 py-2 text-center">
                                    <span
                                        class="inline-grid h-7 w-10 place-items-center rounded-md text-xs font-bold"
                                        :class="hurufKelas(id)"
                                        x-text="huruf(id)"
                                    ></span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 5 + $komponen->count() }}" class="px-5 py-12">
                                    <x-empty-state
                                        title="Belum ada peserta"
                                        description="Mahasiswa muncul di sini setelah rencana studi mereka disetujui Dosen Wali."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($lembar->isNotEmpty() && $kelas->status_nilai !== 'final' && $periodeDibuka)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4">
                    <p class="text-[12px] text-ink-muted">
                        Nilai akhir dihitung ulang di server saat disimpan; angka di layar hanya pratinjau.
                    </p>
                    <x-button type="submit">Simpan Nilai</x-button>
                </div>
            @endif
        </x-card>
    </form>

@endsection
