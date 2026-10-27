@php
    use App\Support\Format;

    $f = $laporan->filter;
    $saring = array_filter([
        'term' => $f->term->id,
        'gedung' => $f->gedungId,
        'jenis' => $f->jenis,
        'urut' => $f->urut,
    ], fn ($v) => $v !== null);

    $persen = fn (?float $v): string => $v === null ? '—' : Format::angka($v, 1).'%';

    // Lima tingkat, ditulis utuh supaya Tailwind menemukan kelasnya.
    $warnaSel = fn (?float $v): string => match (true) {
        $v === null => 'bg-line/40 text-ink-faint',
        $v <= 0.0 => 'bg-surface text-ink-faint',
        $v < 0.25 => 'bg-navy/10 text-ink',
        $v < 0.5 => 'bg-navy/25 text-ink',
        $v < 0.75 => 'bg-navy/55 text-canvas',
        default => 'bg-navy text-canvas',
    };
@endphp
@extends('layouts.app')

@section('title', __('pemanfaatan_ruang.judul'))

@section('aksi')
    <x-button href="{{ route('admin.pemanfaatan-ruang.kosong', ['term' => $f->term->id]) }}" variant="outline" size="sm">
        {{ __('pemanfaatan_ruang.aksi.cari_kosong') }}
    </x-button>
    <x-button href="{{ route('admin.pemanfaatan-ruang.ekspor', $saring) }}" size="sm">
        {{ __('pemanfaatan_ruang.aksi.ekspor') }}
    </x-button>
@endsection

@section('content')
    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]">
            <x-field :label="__('pemanfaatan_ruang.field.term')" name="term" :options="$daftarTerm" :value="$f->term->id" required />
            <x-field :label="__('pemanfaatan_ruang.field.gedung')" name="gedung" :options="$daftarGedung" :value="$f->gedungId" />
            <x-field :label="__('pemanfaatan_ruang.field.jenis')" name="jenis" :options="$jenisPilihan" :value="$f->jenis" />
            <x-field :label="__('pemanfaatan_ruang.field.urut')" name="urut" :options="$urutPilihan" :value="$f->urut" required />
            <div class="flex items-end"><x-button type="submit">{{ __('pemanfaatan_ruang.aksi.tampilkan') }}</x-button></div>
        </form>
        <p class="tabular mt-3 text-[12px] text-ink-muted">
            {{ __('pemanfaatan_ruang.bingkai', [
                'hari' => Format::daftarHari($laporan->hariOperasional),
                'jam' => Format::rentangJam($laporan->jamMulai, $laporan->jamSelesai),
                'minggu' => Format::angka($laporan->jumlahMinggu, 1),
                'libur' => Format::bulat($laporan->hariLibur),
            ]) }}
        </p>
    </x-card>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('pemanfaatan_ruang.kartu.ruang')" :value="Format::bulat($laporan->ruang->count())" />
        <x-stat-card :label="__('pemanfaatan_ruang.kartu.rerata')" :value="$persen($laporan->rerataPemanfaatan())" feature />
        <x-stat-card :label="__('pemanfaatan_ruang.kartu.kelebihan')" :value="Format::bulat($laporan->kelebihan->count())" />
        <x-stat-card :label="__('pemanfaatan_ruang.kartu.jarang')" :value="Format::bulat($laporan->jarang()->count())"
            :meta="__('pemanfaatan_ruang.kartu.jarang_meta', ['ambang' => $laporan->ambangJarang])" />
    </div>

    <x-card flush class="mb-5" :title="__('pemanfaatan_ruang.kartu.tabel')" :meta="__('pemanfaatan_ruang.kartu.tabel_meta')">
        @if ($laporan->ruang->isEmpty())
            <x-empty-state :title="__('pemanfaatan_ruang.keterangan.kosong')" :description="__('pemanfaatan_ruang.keterangan.kosong_deskripsi')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.ruang') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.jenis') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.kapasitas') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.tersedia') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.terpakai') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.kosong') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.pemanfaatan') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.okupansi') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.di_luar') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.pemakaian') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($laporan->ruang as $r)
                            <tr @class([
                                'border-b border-line/50 last:border-b-0 odd:bg-zebra',
                                'bg-highlight!' => $dipilih?->uuid === $r->uuid,
                            ])>
                                <td class="px-5 py-3">
                                    <div class="tabular font-medium">{{ $r->kode }}</div>
                                    <div class="text-[11.5px] text-ink-faint">{{ $r->nama }} · {{ $r->gedungKode ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3 text-[12.5px]">{{ $jenisPilihan[$r->jenis] ?? $r->jenis }}</td>
                                <td class="tabular px-5 py-3 text-right">
                                    {{ $r->kapasitas > 0 ? Format::bulat($r->kapasitas) : '—' }}
                                </td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::angka($r->jamTersediaPerMinggu, 1) }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::angka($r->jamTerpakaiPerMinggu, 1) }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::angka($r->jamKosongPerMinggu(), 1) }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-20 overflow-hidden rounded-full bg-line" aria-hidden="true">
                                            <div class="h-full bg-navy" style="width: {{ min(100, (int) round($r->pemanfaatanPersen() ?? 0)) }}%"></div>
                                        </div>
                                        <span class="tabular">{{ $persen($r->pemanfaatanPersen()) }}</span>
                                    </div>
                                </td>
                                <td class="tabular px-5 py-3 text-right" @if ($r->kapasitas === 0) title="{{ __('pemanfaatan_ruang.keterangan.tak_diketahui') }}" @endif>
                                    {{ $persen($r->okupansiPersen) }}
                                </td>
                                <td class="tabular px-5 py-3 text-right {{ $r->jamDiLuarPerMinggu > 0 ? 'text-warning' : 'text-ink-faint' }}">
                                    {{ Format::angka($r->jamDiLuarPerMinggu, 1) }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="tabular text-[12.5px]">
                                        {{ __('pemanfaatan_ruang.keterangan.pemakaian', ['kuliah' => Format::bulat($r->slotKuliah), 'ujian' => Format::bulat($r->sesiUjian)]) }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if ($r->kelebihan > 0)
                                            <x-chip tone="danger">{{ __('pemanfaatan_ruang.keterangan.kelebihan', ['jumlah' => $r->kelebihan]) }}</x-chip>
                                        @endif
                                        @if ($r->jarang)
                                            <x-chip tone="warning">{{ __('pemanfaatan_ruang.keterangan.jarang') }}</x-chip>
                                        @endif
                                        @unless ($r->aktif)
                                            <x-chip>{{ __('pemanfaatan_ruang.keterangan.nonaktif') }}</x-chip>
                                        @endunless
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-button href="{{ route('admin.pemanfaatan-ruang.index', [...$saring, 'ruang' => $r->uuid]) }}#peta" variant="outline" size="sm">
                                        {{ __('pemanfaatan_ruang.aksi.peta') }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($laporan->ruang->isNotEmpty() && $laporan->kolomPeta !== [])
        <x-card flush id="peta" class="mb-5" :title="__('pemanfaatan_ruang.kartu.peta').' · '.($dipilih ? $dipilih->kode.' · '.$dipilih->nama : __('pemanfaatan_ruang.aksi.semua_ruang'))"
            :meta="__('pemanfaatan_ruang.kartu.peta_meta')">
            <form method="GET" action="{{ route('admin.pemanfaatan-ruang.index') }}#peta" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-3">
                @foreach ($saring as $kunci => $nilai)
                    <input type="hidden" name="{{ $kunci }}" value="{{ $nilai }}">
                @endforeach
                <x-field class="w-72" :label="__('pemanfaatan_ruang.field.ruang')" name="ruang"
                    :options="$laporan->ruang->mapWithKeys(fn ($r) => [$r->uuid => $r->kode.' · '.$r->nama])->all()" :value="$dipilih?->uuid" />
                <x-button type="submit" variant="outline" size="sm">{{ __('pemanfaatan_ruang.aksi.tampilkan') }}</x-button>
            </form>

            <div class="overflow-x-auto px-5 py-4">
                <table class="tabular w-full min-w-[720px] border-separate border-spacing-1 text-[11.5px]">
                    <thead>
                        <tr>
                            <th class="w-20 text-left font-semibold text-ink-muted">{{ __('pemanfaatan_ruang.kolom.hari') }}</th>
                            @foreach ($laporan->kolomPeta as $kolom)
                                <th class="font-semibold text-ink-muted">{{ sprintf('%02d.%02d', intdiv($kolom, 60), $kolom % 60) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($laporan->hariOperasional as $hari)
                            <tr>
                                <th class="text-left font-medium">{{ $namaHari[$hari] ?? $hari }}</th>
                                @foreach ($laporan->kolomPeta as $kolom)
                                    @php $nilai = $peta[$hari][$kolom] ?? null; @endphp
                                    <td class="h-9 rounded-control border border-line/60 text-center {{ $warnaSel($nilai) }}"
                                        title="{{ $nilai === null ? __('pemanfaatan_ruang.keterangan.tidak_tersedia') : Format::angka($nilai * 100, 0).'%' }}">
                                        {{ match (true) {
                                            $nilai === null || $nilai <= 0.0 => '',
                                            $nilai < 0.005 => '<1',
                                            default => Format::angka($nilai * 100, 0),
                                        } }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-3 flex items-center gap-2 text-[11.5px] text-ink-muted">
                    <span>{{ __('pemanfaatan_ruang.keterangan.legenda') }}</span>
                    @foreach ([0.0, 0.1, 0.3, 0.6, 1.0] as $contoh)
                        <span class="inline-block h-3.5 w-6 rounded-control border border-line/60 {{ $warnaSel($contoh) }}" aria-hidden="true"></span>
                    @endforeach
                    <span>{{ __('pemanfaatan_ruang.keterangan.legenda_penuh') }}</span>
                </div>
            </div>
        </x-card>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card flush :title="__('pemanfaatan_ruang.kartu.daftar_kelebihan')" :meta="__('pemanfaatan_ruang.kartu.daftar_kelebihan_meta')">
            @if ($laporan->kelebihan->isEmpty())
                <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('pemanfaatan_ruang.keterangan.tanpa_kelebihan') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.ruang') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('pemanfaatan_ruang.kolom.kelas') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.peserta') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ __('pemanfaatan_ruang.kolom.selisih') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($laporan->kelebihan as $k)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="tabular px-5 py-3 font-medium">{{ $k->ruangKode }}</td>
                                    <td class="px-5 py-3">
                                        <div><x-chip>{{ __('pemanfaatan_ruang.jenis_pakai.'.$k->jenis) }}</x-chip> {{ $k->kelas }}</div>
                                        <div class="tabular mt-1 text-[11.5px] text-ink-faint">{{ $k->waktu }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3 text-right">{{ Format::bulat($k->peserta) }} / {{ Format::bulat($k->kapasitas) }}</td>
                                    <td class="tabular px-5 py-3 text-right text-danger">+{{ Format::bulat($k->selisih()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card flush :title="__('pemanfaatan_ruang.kartu.daftar_jarang')"
            :meta="__('pemanfaatan_ruang.kartu.daftar_jarang_meta', ['ambang' => $laporan->ambangJarang])">
            @if ($laporan->jarang()->isEmpty())
                <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('pemanfaatan_ruang.keterangan.tanpa_jarang') }}</p>
            @else
                <ul class="divide-y divide-line/60 text-[13px]">
                    @foreach ($laporan->jarang() as $r)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div>
                                <span class="tabular font-medium">{{ $r->kode }}</span>
                                <span class="text-ink-faint">· {{ $r->nama }}</span>
                            </div>
                            <span class="tabular text-warning">
                                {{ $persen($r->pemanfaatanPersen()) }}
                                <span class="text-ink-faint">· {{ __('pemanfaatan_ruang.keterangan.jam', ['jam' => Format::angka($r->jamTerpakaiPerMinggu, 1)]) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
