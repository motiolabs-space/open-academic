@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Beban Kerja Dosen')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <x-card class="mb-5">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,260px)_auto]">
            <form method="GET" class="flex items-end gap-2">
                <x-field label="Semester" name="semester" :options="$semesterPilihan"
                    :value="$term->kode" required class="min-w-[200px]" />
                <x-button type="submit" size="sm" variant="outline">Tampilkan</x-button>
            </form>

            <div class="flex flex-wrap items-end gap-2">
                {{-- Ekspor diletakkan di depan, bukan disembunyikan di menu:
                     selama sambungan SISTER belum ada, inilah cara data ini
                     keluar dari gedung. --}}
                <x-button href="{{ route('admin.bkd.ekspor.rekap', ['semester' => $term->kode]) }}"
                    variant="outline" size="sm">Ekspor rekap BKD (CSV)</x-button>
                <x-button href="{{ route('admin.bkd.ekspor.kegiatan', ['semester' => $term->kode]) }}"
                    variant="outline" size="sm">Ekspor kegiatan dosen (CSV)</x-button>
            </div>
        </div>
    </x-card>

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <x-stat-card label="Belum melapor" :value="$belumMelapor->count()" meta="dosen wajib BKD"
            :feature="$belumMelapor->isNotEmpty()" />
        <x-stat-card label="Laporan masuk" :value="$laporan->count()" meta="pada semester ini" />
        <x-stat-card label="Rentang kampus"
            :value="Format::angka($batas['minimum_ratus'] / 100, 0).'–'.Format::angka($batas['maksimum_ratus'] / 100, 0)"
            meta="SKS" />
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <x-card flush title="Laporan" :meta="$perStatus->map(fn ($n, $s) => $s.': '.$n)->implode(' · ') ?: '—'">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Dosen</th>
                            <th class="px-5 py-3 text-right font-semibold">Total SKS</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Asesor</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($laporan as $l)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $l->dosen->namaLengkap() }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">
                                        {{ $l->dosen->nidn ?? '—' }} · {{ $l->dosen->prodi?->nama ?? '—' }}
                                    </div>
                                </td>
                                <td class="tabular px-5 py-3 text-right">
                                    <span @class([
                                        'font-medium',
                                        'text-warning-ink' => $l->sks_total < $batas['minimum_ratus']
                                            || $l->sks_total > $batas['maksimum_ratus'],
                                    ])>{{ Format::angka($l->sksTotal(), 2) }}</span>
                                    <div class="text-[11px] text-ink-faint">
                                        {{ Format::angka($l->sks_pendidikan / 100, 2) }} /
                                        {{ Format::angka($l->sks_penelitian / 100, 2) }} /
                                        {{ Format::angka($l->sks_pengabdian / 100, 2) }} /
                                        {{ Format::angka($l->sks_penunjang / 100, 2) }}
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$l->status->tone()">{{ $l->status->label() }}</x-chip>
                                    @if ($l->kesimpulan)
                                        <div class="mt-1">
                                            <x-chip :tone="$l->kesimpulan->tone()">{{ $l->kesimpulan->label() }}</x-chip>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($l->asesor1)
                                        <div>{{ $l->asesor1->nama }}</div>
                                        <div class="text-[11.5px] text-ink-faint">{{ $l->asesor2?->nama ?? '—' }}</div>
                                    @elseif ($bolehKelola)
                                        <form method="POST" action="{{ route('admin.bkd.asesor', $l) }}" class="space-y-2">
                                            @csrf
                                            <x-field label="Asesor I" name="asesor_1" :options="$asesorPilihan" required />
                                            <x-field label="Asesor II" name="asesor_2" :options="$asesorPilihan" />
                                            <x-button type="submit" size="sm" variant="outline">Tetapkan</x-button>
                                        </form>
                                    @else
                                        <span class="text-ink-faint">Belum ditetapkan</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <x-button href="{{ route('admin.bkd.unduh', $l) }}" variant="outline" size="sm">
                                            PDF
                                        </x-button>

                                        <x-button href="{{ route('admin.bkd.ekspor.portofolio', ['dosen' => $l->dosen, 'semester' => $term->kode]) }}"
                                            variant="ghost" size="sm">JSON</x-button>

                                        @if ($bolehKelola && $l->status->value === 'dinilai')
                                            <form method="POST" action="{{ route('admin.bkd.sahkan', $l) }}">
                                                @csrf
                                                <x-button type="submit" size="sm">Sahkan</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state
                                        title="Belum ada laporan"
                                        description="Laporan muncul di sini setelah dosen mengajukannya dari portal masing-masing." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="space-y-5">
            <x-card title="Belum Melapor" :meta="$belumMelapor->count().' dosen'">
                @if ($belumMelapor->isEmpty())
                    <p class="text-[13px] text-ink-muted">
                        Seluruh dosen yang wajib BKD sudah mengajukan laporannya.
                    </p>
                @else
                    <ul class="space-y-1.5 text-[13px]">
                        @foreach ($belumMelapor as $d)
                            <li class="flex items-baseline justify-between gap-3 border-b border-line/50 pb-1.5 last:border-b-0">
                                <span>{{ $d->nama }}</span>
                                <span class="tabular text-[11.5px] text-ink-faint">{{ $d->nidn ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Sebelum Ada Sambungan SISTER">
                {{-- Dinyatakan terus terang. Menyebut modul ini "terintegrasi
                     SISTER" sebelum kredensialnya ada akan membuat seseorang
                     berhenti menyiapkan datanya. --}}
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    Open Academic belum tersambung ke SISTER — kredensialnya belum ada.
                    Yang sudah siap adalah <strong>datanya</strong>: portofolio, kegiatan
                    beserta luaran, dan rekap BKD per semester.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-muted">
                    Ekspor CSV untuk borang dan lembar kerja; ekspor JSON per dosen adalah
                    bentuk yang akan dikonsumsi skrip integrasi nanti, dan sengaja dapat
                    diunduh sekarang agar pemetaannya bisa ditulis atas data sungguhan.
                </p>
            </x-card>
        </div>
    </div>

    {{-- ============ KELOMPOK DATA SISTER ============ --}}
    <x-card
        class="mt-5"
        title="Kelompok Data SISTER"
        meta="Per kelompok, bukan satu berkas"
        flush
    >
        {{--
            Kelompok yang tidak menghasilkan apa-apa ikut ditampilkan beserta
            alasannya. Daftar yang hanya memuat yang berhasil akan membuat
            kampus mengira portofolionya lengkap karena semua yang terlihat
            hijau.
        --}}
        @foreach ($sisterKatalog as $kunci => $grup)
            <div class="flex flex-wrap items-center gap-3 border-b border-line/50 px-5 py-3 last:border-b-0">
                <span class="w-52 flex-none truncate text-[12.5px] font-medium">{{ $grup['label'] }}</span>

                @if ($grup['tersedia'])
                    <span class="tabular w-20 flex-none text-[12px] text-ink-muted">
                        {{ Format::bulat($grup['baris']) }} baris
                    </span>

                    {{-- Nol baris pada kelompok yang tak punya layar pengisian
                         adalah angka yang benar dengan kesimpulan yang salah. --}}
                    <span class="min-w-0 flex-1 text-[12px] text-ink-faint">{{ $grup['catatan'] }}</span>

                    <x-button
                        href="{{ route('admin.bkd.ekspor.sister', $kunci) }}"
                        variant="outline"
                        class="px-4 py-1.5 text-xs"
                    >
                        Unduh CSV
                    </x-button>
                @else
                    <x-chip tone="neutral">Belum</x-chip>
                    <span class="min-w-0 flex-1 text-[12.5px] text-ink-muted">{{ $grup['alasan'] }}</span>
                @endif
            </div>
        @endforeach
    </x-card>
@endsection
