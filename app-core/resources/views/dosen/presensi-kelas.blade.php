@extends('layouts.app')

@section('title', 'Presensi Kelas')

@php use App\Support\Format; @endphp

@section('content')
    @php
        $aktifQr = $pertemuan->first(fn ($p) => $p->qrAktif());
        $rawan = collect($rekap)->filter(fn (?float $p): bool => $p !== null && $p < $minimum)->count();
    @endphp

    @if ($aktifQr)
        {{-- Sesi QR: token berputar dan kedaluwarsa dalam hitungan menit, agar
             tangkapan layar yang diteruskan ke teman yang absen keburu mati. --}}
        <x-card class="mb-5">
            <div class="flex flex-wrap items-center gap-5">
                <div class="guilloche-navy grid h-28 w-28 flex-none place-items-center rounded-card border-2 border-navy bg-surface">
                    <span class="px-2 text-center font-mono text-[9px] leading-tight text-navy">
                        {{ Str::limit($aktifQr->qr_token, 32, '') }}
                    </span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="animate-heartbeat text-[10px] text-success" aria-hidden="true">●</span>
                        <h2 class="text-[15px] font-semibold">Sesi presensi mandiri terbuka</h2>
                    </div>
                    <p class="mt-1 text-[13px] text-ink-muted">
                        Pertemuan {{ $aktifQr->pertemuan_ke }} · berakhir
                        {{ Format::jam($aktifQr->qr_expires_at) }} WIB
                        ({{ $aktifQr->sisaDetikQr() }} detik lagi)
                    </p>
                </div>

                <form method="POST" action="{{ route('dosen.presensi.qr.tutup', [$kelas, $aktifQr]) }}">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit">Tutup Sesi</x-button>
                </form>
            </div>
        </x-card>
    @endif

    @if ($rawan > 0)
        <x-alert tone="warning" class="mb-5">
            <strong>{{ $rawan }} mahasiswa di bawah kehadiran minimum {{ (int) $minimum }}%.</strong>
            Baris mereka ditandai merah pada kolom rekap.
        </x-alert>
    @endif

    {{-- ============ REKAP PER MAHASISWA ============ --}}
    <x-card class="mb-5" title="Rekap Kehadiran" :meta="$peserta->count().' mahasiswa'" flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="sticky left-0 z-[1] bg-surface px-4 py-3 font-semibold">Mahasiswa</th>
                        @foreach ($pertemuan as $p)
                            <th class="px-1.5 py-3 text-center font-semibold" title="{{ Format::tanggal($p->tanggal) }}">
                                {{ $p->pertemuan_ke }}
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-right font-semibold">Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peserta as $mahasiswa)
                        @php $persen = $rekap[$mahasiswa->id] ?? null; @endphp

                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="sticky left-0 z-[1] bg-inherit px-4 py-2">
                                <div class="font-medium">{{ $mahasiswa->nama }}</div>
                                <div class="tabular text-[11px] text-ink-faint">{{ $mahasiswa->nim }}</div>
                            </td>

                            @foreach ($pertemuan as $p)
                                @php $mark = $tanda[$mahasiswa->id][$p->id] ?? null; @endphp

                                <td class="px-1.5 py-2 text-center">
                                    @if ($mark)
                                        <span @class([
                                            'inline-grid h-6 w-6 place-items-center rounded text-[11px] font-bold',
                                            'bg-success-bg text-success' => $mark->status->value === 'H',
                                            'bg-warning-bg text-warning' => in_array($mark->status->value, ['I', 'S'], true),
                                            'bg-danger-bg text-danger' => $mark->status->value === 'A',
                                        ])>{{ $mark->status->value }}</span>
                                    @else
                                        <span class="text-ink-faint">·</span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="tabular px-4 py-2 text-right">
                                @if ($persen === null)
                                    <span class="text-ink-faint">—</span>
                                @else
                                    <span class="{{ $persen < $minimum ? 'font-semibold text-danger' : '' }}">
                                        {{ Format::angka($persen, 0) }}%
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + $pertemuan->count() }}" class="px-5 py-12">
                                <x-empty-state
                                    title="Belum ada peserta"
                                    description="Mahasiswa muncul setelah rencana studi mereka disetujui Dosen Wali."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- ============ ISI PER PERTEMUAN ============ --}}
    @if ($peserta->isNotEmpty())
        <x-card title="Isi Presensi per Pertemuan" flush x-data="{ dipilih: null }">
            <div class="flex flex-wrap gap-1.5 border-b border-line px-5 py-4">
                @foreach ($pertemuan as $p)
                    <button
                        type="button"
                        @click="dipilih = dipilih === {{ $p->id }} ? null : {{ $p->id }}"
                        :class="dipilih === {{ $p->id }} ? 'bg-navy text-canvas' : '{{ $p->is_terlaksana ? 'bg-success-bg text-success' : 'bg-line/60 text-ink-muted' }}'"
                        class="tabular grid h-10 w-10 place-items-center rounded-lg text-[13px] font-semibold"
                        title="{{ Format::tanggal($p->tanggal) }}"
                    >{{ $p->pertemuan_ke }}</button>
                @endforeach
            </div>

            @foreach ($pertemuan as $p)
                <div x-show="dipilih === {{ $p->id }}" x-cloak class="px-5 py-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-[14px] font-semibold">Pertemuan {{ $p->pertemuan_ke }}</div>
                            <div class="tabular text-[12px] text-ink-muted">
                                {{ Format::tanggalHari($p->tanggal) }} · {{ $p->topik ?? 'Tanpa topik' }}
                            </div>
                        </div>

                        @unless ($p->qrAktif())
                            <form method="POST" action="{{ route('dosen.presensi.qr.buka', [$kelas, $p]) }}">
                                @csrf
                                <x-button variant="outline" type="submit" class="px-4 py-2 text-xs">
                                    Buka Sesi QR
                                </x-button>
                            </form>
                        @endunless
                    </div>

                    <form method="POST" action="{{ route('dosen.presensi.simpan', [$kelas, $p]) }}">
                        @csrf

                        <div class="divide-y divide-line/50 rounded-card border border-line">
                            @foreach ($peserta as $mahasiswa)
                                @php $mark = $tanda[$mahasiswa->id][$p->id] ?? null; @endphp

                                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                                    <div class="min-w-0 flex-1">
                                        <span class="text-[13px] font-medium">{{ $mahasiswa->nama }}</span>
                                        <span class="tabular ml-2 text-[11.5px] text-ink-faint">{{ $mahasiswa->nim }}</span>
                                    </div>

                                    <div class="flex gap-1">
                                        @foreach ($statusPilihan as $status)
                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="status[{{ $mahasiswa->id }}]"
                                                    value="{{ $status->value }}"
                                                    class="peer sr-only"
                                                    @checked(($mark?->status->value ?? 'H') === $status->value)
                                                >
                                                <span @class([
                                                    'grid h-9 w-9 place-items-center rounded-lg border text-[12px] font-semibold',
                                                    'border-line-input text-ink-muted' => true,
                                                    'peer-checked:border-success peer-checked:bg-success-bg peer-checked:text-success' => $status->value === 'H',
                                                    'peer-checked:border-warning peer-checked:bg-warning-bg peer-checked:text-warning' => in_array($status->value, ['I', 'S'], true),
                                                    'peer-checked:border-danger peer-checked:bg-danger-bg peer-checked:text-danger' => $status->value === 'A',
                                                ]) title="{{ $status->label() }}">{{ $status->value }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex justify-end">
                            <x-button type="submit">Simpan Pertemuan {{ $p->pertemuan_ke }}</x-button>
                        </div>
                    </form>
                </div>
            @endforeach
        </x-card>
    @endif
@endsection
