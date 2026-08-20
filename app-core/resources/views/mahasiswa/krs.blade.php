@extends('layouts.app')

@section('title', 'Rencana Studi')

@php use App\Support\Format; @endphp

@section('content')
    {{-- Status tracker: Draft → Diajukan → Ditinjau Wali → Disetujui --}}
    @php
        $tahapan = ['Draft', 'Diajukan', 'Ditinjau Wali', 'Disetujui'];
        $tahapAktif = match ($krs->status->value) {
            'draft', 'ditolak' => 0,
            'diajukan' => 2,
            'disetujui' => 3,
            default => 0,
        };
    @endphp

    <x-card class="mb-5">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-3">
            @foreach ($tahapan as $i => $label)
                <li class="flex items-center gap-2">
                    <span @class([
                        'grid h-6 w-6 place-items-center rounded-full text-[11px] font-bold',
                        'bg-navy text-canvas' => $i < $tahapAktif,
                        'bg-gold text-navy' => $i === $tahapAktif,
                        'bg-line text-ink-faint' => $i > $tahapAktif,
                    ])>{{ $i < $tahapAktif ? '✓' : $i + 1 }}</span>

                    <span @class([
                        'text-[12.5px]',
                        'font-semibold text-ink' => $i <= $tahapAktif,
                        'text-ink-faint' => $i > $tahapAktif,
                    ])>{{ $label }}</span>
                </li>

                @if (! $loop->last)
                    <li class="h-px w-6 flex-none bg-line" aria-hidden="true"></li>
                @endif
            @endforeach
        </ol>
    </x-card>

    @if ($krs->status->value === 'ditolak' && $krs->catatan_wali)
        <x-alert tone="danger" class="mb-5">
            <strong>Rencana studi dikembalikan oleh Dosen Wali.</strong>
            {{ $krs->catatan_wali }}
        </x-alert>
    @endif

    @if ($ringkasan->alasanTidakDapatDiajukan)
        <x-alert tone="warning" class="mb-5">{{ $ringkasan->alasanTidakDapatDiajukan }}</x-alert>
    @endif

    {{-- Alasan tiap mata kuliah yang dilewati paket. Satu angka
         ("6 ditambahkan") menyembunyikan justru bagian yang perlu
         ditindaklanjuti mahasiswa. --}}
    @if (session('paket_dilewati'))
        <x-alert tone="warning" class="mb-5">
            <strong>Sebagian mata kuliah paket tidak dapat ditambahkan.</strong>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-[12.5px]">
                @foreach (session('paket_dilewati') as $lewat)
                    <li>{{ $lewat['mata_kuliah'] }} — {{ $lewat['alasan'] }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    {{-- Hanya untuk prodi yang menerbitkan rencana studi. Yang lain tidak
         melihat apa pun di sini. --}}
    @if ($paket && $paket['paket'])
        <x-card class="mb-5" title="Paket Semester" :meta="$paket['paket']->nama">
            <p class="text-[13px] text-ink-muted">
                Program studi Anda menerbitkan rencana studi. Menerapkan paket menambahkan
                {{ $paket['baris']->count() }} mata kuliah sekaligus — aturan yang sama tetap
                berlaku, jadi mata kuliah yang sudah Anda lulusi atau yang kelasnya penuh akan
                dilewati beserta alasannya.
            </p>

            <ul class="tabular mt-3 grid gap-1 text-[12.5px] text-ink-muted sm:grid-cols-2">
                @foreach ($paket['baris'] as $baris)
                    <li class="flex items-center gap-2">
                        <span class="font-semibold">{{ $baris['mata_kuliah']->kode }}</span>
                        <span class="truncate">{{ $baris['mata_kuliah']->nama }}</span>
                        @if (! $baris['kelas'])
                            <x-chip tone="neutral">belum ada kelas</x-chip>
                        @endif
                    </li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('mahasiswa.krs.paket') }}" class="mt-4">
                @csrf
                <x-button type="submit" class="px-4 py-2 text-xs">Terapkan paket</x-button>
            </form>
        </x-card>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1.6fr_1fr]">
        {{-- ============ KATALOG ============ --}}
        <x-card title="Katalog Mata Kuliah" :meta="$katalog->total().' kelas ditawarkan'" flush>
            {{-- Pencarian ada BERSAMA paginasi, bukan sesudahnya: layar ini
                 dipakai untuk mencari mata kuliah tertentu, dan membolak-balik
                 dua puluh halaman demi satu nama lebih buruk daripada halaman
                 panjang yang bisa di-Ctrl+F. --}}
            <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
                <label class="min-w-[200px] flex-1">
                    <span class="sr-only">Cari mata kuliah</span>
                    <input
                        type="search"
                        name="cari"
                        value="{{ $cari }}"
                        placeholder="Cari kode atau nama mata kuliah…"
                        class="w-full rounded-control border border-line-input bg-canvas px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10"
                    >
                </label>

                <x-button type="submit" variant="outline" size="sm">Cari</x-button>

                @if ($cari !== '')
                    <x-button :href="route('mahasiswa.krs')" variant="ghost" size="sm">Hapus filter</x-button>
                @endif
            </form>

            <div class="divide-y divide-line/60">
                @forelse ($katalog as $baris)
                    @php
                        $kelas = $baris['kelas'];
                        $jadwal = $kelas->jadwal->first();
                        $persenKuota = $kelas->kuota > 0 ? min(100, round($kelas->terisi / $kelas->kuota * 100)) : 100;
                    @endphp

                    <div @class([
                        'flex flex-wrap items-start gap-3 px-5 py-4',
                        'bg-highlight' => $baris['sudah_diambil'],
                    ])>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                {{-- Kode mata kuliah dan sisa kursi memakai ink-muted, bukan
                                     ink-faint: keduanya informasi yang tidak ada di tempat lain
                                     di layar ini, dan aturan 8 di docs/DESIGN.md melarang
                                     ink-faint untuk itu. --}}
                                <span class="tabular text-[11.5px] font-semibold text-ink-muted">
                                    {{ $kelas->mataKuliah->kode }}
                                </span>
                                <span class="text-[13.5px] font-semibold">{{ $kelas->mataKuliah->nama }}</span>
                                <x-chip tone="neutral">{{ $kelas->sks }} SKS</x-chip>

                                @if ($kelas->kelasKolaboratif())
                                    <x-chip tone="gold">IKU 7</x-chip>
                                @endif
                            </div>

                            <div class="tabular mt-1 text-xs text-ink-muted">
                                Kelas {{ $kelas->kode }} ·
                                {{ $jadwal?->rentangWaktu() ?? 'Jadwal belum diatur' }} ·
                                {{ $jadwal?->ruang?->kode ?? 'Daring' }} ·
                                {{ $kelas->dosenPengampu->first()?->nama ?? '—' }}
                            </div>

                            {{-- Kuota sebagai bar, bukan angka telanjang: mahasiswa
                                 perlu tahu seberapa mendesak mengambilnya. --}}
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 w-28 overflow-hidden rounded-full bg-line">
                                    <div @class([
                                        'h-full rounded-full',
                                        'bg-danger' => $persenKuota >= 100,
                                        'bg-warning' => $persenKuota >= 80 && $persenKuota < 100,
                                        'bg-navy' => $persenKuota < 80,
                                    ]) style="width: {{ $persenKuota }}%"></div>
                                </div>
                                <span class="tabular text-[11.5px] text-ink-muted">
                                    {{ $kelas->terisi }}/{{ $kelas->kuota }} kursi
                                </span>
                            </div>

                            {{-- Tidak ditampilkan pada kelas yang sudah diambil:
                                 peringatan prasyarat di baris berlabel "Diambil"
                                 membingungkan dan tidak dapat ditindaklanjuti. --}}
                            @if ($baris['belum_prasyarat'] && ! $baris['sudah_diambil'])
                                <div class="mt-2 text-[11.5px] text-danger">
                                    Prasyarat belum lulus: {{ implode(', ', $baris['belum_prasyarat']) }}
                                </div>
                            @endif

                            {{-- Dua sebab, dan mahasiswa hanya dapat menindaklanjuti
                                 salah satunya — jadi keduanya dibedakan di sini. --}}
                            @if ($baris['luar_konsentrasi'] && ! $baris['sudah_diambil'])
                                <div class="mt-2 text-[11.5px] text-muted">
                                    @if ($krs->mahasiswa->konsentrasi_id === null)
                                        Mata kuliah ini milik salah satu konsentrasi. Tetapkan konsentrasi
                                        Anda lebih dulu lewat dosen wali atau bagian akademik.
                                    @else
                                        Bukan untuk konsentrasi {{ $krs->mahasiswa->konsentrasi?->nama }}.
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="flex-none">
                            @if ($baris['sudah_diambil'])
                                <x-chip tone="success" dot>Diambil</x-chip>
                            @elseif ($baris['sudah_lulus'])
                                <x-chip tone="info">Sudah lulus</x-chip>
                            @elseif ($baris['dapat_diambil'])
                                <form method="POST" action="{{ route('mahasiswa.krs.tambah', $kelas) }}">
                                    @csrf
                                    <x-button variant="outline" type="submit" class="px-4 py-2 text-xs">
                                        Ambil
                                    </x-button>
                                </form>
                            @else
                                <x-chip tone="neutral">
                                    {{-- Konsentrasi didahulukan: alasannya struktural,
                                         dan "kuota penuh" pada mata kuliah yang memang
                                         bukan untuk jalur ini menyesatkan. --}}
                                    @if ($baris['luar_konsentrasi']) Luar konsentrasi
                                    @elseif ($baris['penuh']) Kuota penuh
                                    @elseif ($baris['melebihi_batas']) Melebihi batas SKS
                                    @elseif ($baris['belum_prasyarat']) Terkunci prasyarat
                                    @else Tidak tersedia
                                    @endif
                                </x-chip>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10">
                        {{-- Dua keadaan kosong yang berbeda, dan membedakannya penting:
                             "pencarian tidak ketemu" dapat ditindaklanjuti mahasiswa,
                             "belum dibuka akademik" tidak. --}}
                        @if ($cari !== '')
                            <x-empty-state
                                title="Tidak ada yang cocok"
                                :description="'Tidak ada kelas yang kode atau namanya memuat &quot;'.$cari.'&quot; pada kurikulum Anda semester ini.'"
                            >
                                <x-button :href="route('mahasiswa.krs')" variant="outline" size="sm">
                                    Tampilkan semua kelas
                                </x-button>
                            </x-empty-state>
                        @else
                            <x-empty-state
                                title="Belum ada kelas ditawarkan"
                                description="Kelas untuk kurikulum Anda pada semester ini belum dibuka oleh bagian akademik."
                            />
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($katalog->hasPages())
                <div class="border-t border-line px-5 py-3">{{ $katalog->links() }}</div>
            @endif
        </x-card>

        {{-- ============ TRAY SKS ============ --}}
        <div class="lg:sticky lg:top-[76px] lg:self-start">
            <x-card flush>
                <x-slot:title>Rencana Studi Anda</x-slot:title>
                <x-slot:meta>{{ $krs->status->label() }}</x-slot:meta>

                <div class="border-b border-line px-5 py-4">
                    <div class="flex items-end justify-between">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                Total SKS
                            </div>
                            <div class="tabular font-serif text-[30px] font-semibold leading-none">
                                {{ $ringkasan->totalSks }}<span class="text-[18px] text-ink-faint">/{{ $ringkasan->batasSks }}</span>
                            </div>
                        </div>

                        <div class="text-right text-[11.5px] text-ink-faint">
                            @if ($ringkasan->ipsAcuan !== null)
                                Batas dari IPS {{ Format::angka($ringkasan->ipsAcuan) }}
                            @else
                                Batas bawaan mahasiswa baru
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-line">
                        <div
                            class="h-full rounded-full {{ $ringkasan->totalSks > $ringkasan->batasSks ? 'bg-danger' : 'bg-navy' }}"
                            style="width: {{ $ringkasan->persenTerisi() }}%"
                        ></div>
                    </div>
                </div>

                <div class="divide-y divide-line/60">
                    @forelse ($krs->detail as $detail)
                        <div class="flex items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[13px] font-semibold">
                                    {{ $detail->kelasKuliah->mataKuliah->nama }}
                                </div>
                                <div class="tabular text-[11.5px] text-ink-faint">
                                    Kelas {{ $detail->kelasKuliah->kode }} · {{ $detail->sks }} SKS ·
                                    {{ $detail->kelasKuliah->jadwal->first()?->namaHari() ?? '—' }}
                                </div>
                            </div>

                            @if ($ringkasan->dapatDiubah)
                                <form method="POST" action="{{ route('mahasiswa.krs.hapus', $detail) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="grid h-8 w-8 place-items-center rounded-lg text-ink-faint hover:bg-danger-bg hover:text-danger"
                                        aria-label="Keluarkan {{ $detail->kelasKuliah->mataKuliah->nama }}"
                                    >×</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-[13px] text-ink-faint">
                            Belum ada kelas dipilih.
                        </div>
                    @endforelse
                </div>

                @if ($ringkasan->dapatDiubah)
                    <div class="border-t border-line px-5 py-4">
                        <form method="POST" action="{{ route('mahasiswa.krs.ajukan') }}">
                            @csrf
                            {{-- Atribut terikat, bukan @disabled(): direktif Blade
                                 di dalam tag komponen dikompilasi sebagai PHP mentah
                                 dan merusak penguraian atribut komponennya. --}}
                            <x-button
                                type="submit"
                                class="w-full"
                                :disabled="! $ringkasan->dapatDiajukan"
                            >Ajukan ke Dosen Wali</x-button>
                        </form>

                        @if (! $ringkasan->dapatDiajukan && $ringkasan->totalSks === 0)
                            <p class="mt-2 text-center text-[11.5px] text-ink-faint">
                                Pilih minimal satu kelas terlebih dahulu.
                            </p>
                        @endif
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    {{-- Signature moment: an official document being stamped, not confetti. --}}
    @if (session('krs_diajukan'))
        <div
            x-data="{ tampil: true }"
            x-show="tampil"
            x-cloak
            class="fixed inset-0 z-40 grid place-items-center bg-[rgba(16,20,46,0.55)] px-6"
            @click="tampil = false"
            @keydown.escape.window="tampil = false"
        >
            <div class="guilloche-navy w-full max-w-sm rounded-card-lg bg-surface px-8 py-10 text-center shadow-overlay">
                <div class="animate-stamp mx-auto grid h-28 w-28 place-items-center rounded-full border-4 border-gold text-center">
                    <span class="font-serif text-[13px] font-bold uppercase leading-tight tracking-[0.08em] text-gold">
                        KRS<br>Diajukan
                    </span>
                </div>

                <h2 class="mt-6 font-serif text-[20px] font-semibold">Rencana Studi Terkirim</h2>
                <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
                    Menunggu persetujuan Dosen Wali. Anda akan melihat statusnya berubah
                    di halaman ini setelah keputusan diberikan.
                </p>

                <x-button class="mt-6 w-full" @click="tampil = false">Tutup</x-button>
            </div>
        </div>
    @endif
@endsection
