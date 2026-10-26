@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', $kelompok->nama)

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
        <dl class="grid gap-3 text-[13px] sm:grid-cols-3">
            <div><dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.kolom.lokasi') }}</dt><dd>{{ $kelompok->lokasi?->wilayah() ?? '—' }}</dd></div>
            <div><dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.field.pembimbing_mitra_nama') }}</dt>
                <dd>{{ $kelompok->pembimbing_mitra_nama ?? '—' }}
                    @if ($kelompok->pembimbing_mitra_telepon)
                        <span class="tabular text-ink-faint">· {{ $kelompok->pembimbing_mitra_telepon }}</span>
                    @endif
                </dd></div>
            <div><dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.field.bobot_mitra') }}</dt><dd class="tabular">{{ Format::bulat($kelompok->kegiatanLapangan->bobot_mitra) }}%</dd></div>
        </dl>
    </x-card>

    <x-card flush class="mb-5" :title="__('kegiatan_lapangan.kartu.nilai')" :meta="__('kegiatan_lapangan.petunjuk.nilai')">
        <form method="POST" action="{{ route('dosen.kegiatan-lapangan.nilai', $kelompok) }}">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-2.5 font-semibold">{{ __('kegiatan_lapangan.kolom.mahasiswa') }}</th>
                            <th class="px-3 py-2.5 font-semibold">{{ __('kegiatan_lapangan.kolom.nilai_pembimbing') }}</th>
                            <th class="px-3 py-2.5 font-semibold">{{ __('kegiatan_lapangan.kolom.nilai_mitra') }}</th>
                            <th class="px-5 py-2.5 text-right font-semibold">{{ __('kegiatan_lapangan.kolom.nilai_akhir') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggota as $a)
                            @php $terkunci = $a->krsKonversi !== null; @endphp
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-2.5">
                                    <div class="font-medium">{{ $a->mahasiswa->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $a->mahasiswa->nim }} · {{ $a->mahasiswa->prodi->nama }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" step="0.01" min="0" max="100" name="nilai[{{ $a->uuid }}][pembimbing]" value="{{ $a->nilai_pembimbing }}" @disabled($terkunci)
                                        class="tabular w-20 rounded-control border border-line-input bg-surface px-2 py-1 text-[13px]" aria-label="{{ __('kegiatan_lapangan.kolom.nilai_pembimbing') }} {{ $a->mahasiswa->nim }}">
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" step="0.01" min="0" max="100" name="nilai[{{ $a->uuid }}][mitra]" value="{{ $a->nilai_mitra }}" @disabled($terkunci)
                                        class="tabular w-20 rounded-control border border-line-input bg-surface px-2 py-1 text-[13px]" aria-label="{{ __('kegiatan_lapangan.kolom.nilai_mitra') }} {{ $a->mahasiswa->nim }}">
                                </td>
                                <td class="tabular px-5 py-2.5 text-right">
                                    {{ $a->nilai_akhir !== null ? Format::angka((float) $a->nilai_akhir) : '—' }}
                                    @if ($terkunci)
                                        <div><x-chip tone="success">{{ __('kegiatan_lapangan.keterangan.dikonversi') }}</x-chip></div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t border-line px-5 py-3">
                <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.simpan_nilai') }}</x-button>
            </div>
        </form>
    </x-card>

    <x-card flush :title="__('kegiatan_lapangan.kartu.logbook')">
        @php $semua = $anggota->flatMap(fn ($a) => $a->logbook->map(fn ($l) => [$a, $l])); @endphp
        @if ($semua->isEmpty())
            <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('kegiatan_lapangan.keterangan.logbook_kosong') }}</p>
        @else
            <ul class="divide-y divide-line/60 text-[13px]">
                @foreach ($semua->sortByDesc(fn ($p) => $p[1]->tanggal) as [$a, $l])
                    <li class="px-5 py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div>
                                <span class="font-medium">{{ $l->judul }}</span>
                                <span class="text-[11.5px] text-ink-faint">· {{ $l->jenis->label() }} · {{ $a->mahasiswa->nama }}</span>
                            </div>
                            <span class="tabular text-[11.5px] text-ink-muted">{{ Format::tanggal($l->tanggal) }}</span>
                        </div>
                        @if ($l->uraian)
                            <p class="mt-1 whitespace-pre-line text-[12.5px] leading-relaxed">{{ $l->uraian }}</p>
                        @endif
                        @if ($l->berkas_path)
                            <a href="{{ route('berkas.logbook-kegiatan', $l) }}" class="mt-1 inline-block text-[12px] text-navy hover:underline">{{ $l->berkas_nama ?? __('kegiatan_lapangan.keterangan.lampiran') }}</a>
                        @endif
                        @if ($l->sudahDiperiksa())
                            <div class="mt-1"><x-chip tone="success">{{ __('kegiatan_lapangan.keterangan.diperiksa') }}</x-chip>
                                @if ($l->catatan_pembimbing)
                                    <span class="text-[12px] text-ink-muted">{{ $l->catatan_pembimbing }}</span>
                                @endif
                            </div>
                        @else
                            <form method="POST" action="{{ route('dosen.kegiatan-lapangan.periksa', $l) }}" class="mt-2 flex flex-wrap gap-2">
                                @csrf
                                <input type="text" name="catatan" maxlength="2000" placeholder="{{ __('kegiatan_lapangan.petunjuk.catatan') }}"
                                    class="min-w-[220px] flex-1 rounded-control border border-line-input bg-surface px-3 py-1.5 text-[12.5px]" aria-label="{{ __('kegiatan_lapangan.field.catatan') }}">
                                <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.periksa') }}</x-button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endsection
