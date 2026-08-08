@extends('layouts.app')

@section('title', 'Verifikasi Data IKU')

@php use App\Support\Format; @endphp

@section('aksi')
    <x-button
        variant="outline"
        :href="route('admin.iku-records', ['belum' => $belumSaja ? 0 : 1])"
    >{{ $belumSaja ? 'Tampilkan semua' : 'Hanya yang belum diverifikasi' }}</x-button>
@endsection

@section('content')
    <x-alert tone="info" class="mb-5">
        Open Campus hanya membaca catatan <strong>terverifikasi</strong> secara bawaan.
        Verifikasi di sini adalah titik ketika laporan mandiri menjadi bukti — tercatat
        atas nama Anda beserta waktunya.
    </x-alert>

    {{-- ============ AKTIVITAS MBKM (IKU 2) ============ --}}
    <x-card class="mb-6" title="Aktivitas Mahasiswa" meta="Sumber IKU 2" flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                        <th class="px-5 py-3 font-semibold">Jenis</th>
                        <th class="px-5 py-3 font-semibold">Kegiatan</th>
                        <th class="px-5 py-3 font-semibold">Mitra</th>
                        <th class="px-5 py-3 text-center font-semibold">SKS</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($aktivitas as $a)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="px-5 py-2.5">
                                <div class="font-medium">{{ $a->mahasiswa->nama }}</div>
                                <div class="tabular text-[11px] text-ink-faint">
                                    {{ $a->mahasiswa->nim }} · {{ $a->mahasiswa->prodi->nama }}
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-ink-muted">{{ $a->jenis->label() }}</td>
                            <td class="px-5 py-2.5">
                                <div class="max-w-xs truncate">{{ $a->judul }}</div>
                                <div class="tabular text-[11px] text-ink-faint">
                                    {{ Format::tanggal($a->tanggal_mulai) }} – {{ Format::tanggal($a->tanggal_selesai) }}
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-ink-muted">{{ $a->mitra_nama ?? '—' }}</td>
                            <td class="tabular px-5 py-2.5 text-center">
                                <span class="{{ $a->sks_konversi >= $minSksIku2 ? 'font-semibold' : 'text-ink-muted' }}">
                                    {{ $a->sks_konversi }}
                                </span>
                            </td>
                            <td class="px-5 py-2.5">
                                @if ($a->is_verified)
                                    <x-chip tone="success" dot>Terverifikasi</x-chip>
                                    <div class="tabular mt-1 text-[10.5px] text-ink-faint">
                                        {{ $a->verifikator?->nama }} · {{ Format::tanggal($a->verified_at) }}
                                    </div>
                                @else
                                    <x-chip tone="warning">Menunggu</x-chip>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                <form method="POST" action="{{ $a->is_verified
                                    ? route('admin.iku-records.aktivitas.batal', $a)
                                    : route('admin.iku-records.aktivitas.verifikasi', $a) }}">
                                    @csrf
                                    <x-button
                                        :variant="$a->is_verified ? 'ghost' : 'outline'"
                                        type="submit"
                                        class="px-3 py-1.5 text-xs"
                                    >{{ $a->is_verified ? 'Cabut' : 'Verifikasi' }}</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10">
                                <x-empty-state
                                    title="Tidak ada aktivitas menunggu verifikasi"
                                    description="Catatan MBKM mahasiswa muncul di sini untuk diverifikasi sebelum menjadi bukti IKU 2."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- ============ PENUGASAN DOSEN (IKU 3 & 4) ============ --}}
    <x-card title="Penugasan Dosen" meta="Sumber IKU 3 & 4" flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Dosen</th>
                        <th class="px-5 py-3 font-semibold">Jenis</th>
                        <th class="px-5 py-3 font-semibold">Kegiatan</th>
                        <th class="px-5 py-3 font-semibold">Mitra</th>
                        <th class="px-5 py-3 text-center font-semibold">IKU</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($penugasan as $p)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="px-5 py-2.5">
                                <div class="font-medium">{{ $p->dosen->nama }}</div>
                                <div class="tabular text-[11px] text-ink-faint">
                                    {{ $p->dosen->nidn ? 'NIDN '.$p->dosen->nidn : 'Tanpa NIDN' }}
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-ink-muted">{{ $p->jenis->label() }}</td>
                            <td class="px-5 py-2.5">
                                <div class="max-w-xs truncate">{{ $p->judul }}</div>
                                <div class="tabular text-[11px] text-ink-faint">
                                    {{ Format::tanggal($p->tanggal_mulai) }}
                                    @if ($p->sks_ekuivalen)
                                        · {{ Format::angka($p->sks_ekuivalen) }} SKS ekuivalen
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-ink-muted">{{ $p->mitra_nama ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-center">
                                <div class="flex justify-center gap-1">
                                    @if ($p->jenis->countsForIku3())
                                        <x-chip tone="gold">3</x-chip>
                                    @endif
                                    @if ($p->jenis->countsForIku4())
                                        <x-chip tone="gold">4</x-chip>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-2.5">
                                @if ($p->is_verified)
                                    <x-chip tone="success" dot>Terverifikasi</x-chip>
                                    <div class="tabular mt-1 text-[10.5px] text-ink-faint">
                                        {{ $p->verifikator?->nama }} · {{ Format::tanggal($p->verified_at) }}
                                    </div>
                                @else
                                    <x-chip tone="warning">Menunggu</x-chip>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                <form method="POST" action="{{ $p->is_verified
                                    ? route('admin.iku-records.penugasan.batal', $p)
                                    : route('admin.iku-records.penugasan.verifikasi', $p) }}">
                                    @csrf
                                    <x-button
                                        :variant="$p->is_verified ? 'ghost' : 'outline'"
                                        type="submit"
                                        class="px-3 py-1.5 text-xs"
                                    >{{ $p->is_verified ? 'Cabut' : 'Verifikasi' }}</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10">
                                <x-empty-state
                                    title="Tidak ada penugasan menunggu verifikasi"
                                    description="Penugasan dosen di luar kampus dan praktisi mengajar muncul di sini."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
