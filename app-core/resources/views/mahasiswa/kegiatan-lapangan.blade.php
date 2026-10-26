@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('kegiatan_lapangan.judul_mahasiswa'))

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($terbuka->isNotEmpty())
        <x-card flush class="mb-5" :title="__('kegiatan_lapangan.kartu.pendaftaran')">
            <ul class="divide-y divide-line/60 text-[13px]">
                @foreach ($terbuka as $k)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <div class="font-medium">{{ $k->nama }}</div>
                            <div class="text-[11.5px] text-ink-faint">
                                {{ $k->jenis->label() }} · {{ Format::tanggal($k->tanggal_mulai) }} – {{ Format::tanggal($k->tanggal_selesai) }}
                                @if ($k->min_sks > 0)
                                    · {{ __('kegiatan_lapangan.keterangan.min_sks', ['min' => $k->min_sks, 'sks' => $sksSaatIni]) }}
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('mahasiswa.kegiatan-lapangan.daftar', $k) }}">
                            @csrf
                            <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.daftar') }}</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @forelse ($partisipasi as $a)
        @php $k = $a->kegiatanLapangan; $kelompok = $a->kelompok; @endphp
        <div class="mb-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0 space-y-5">
                <x-card :title="$k->nama" :meta="$k->jenis->label().' · '.$k->tahunAkademik->nama">
                    @if ($kelompok === null)
                        <x-alert tone="info">{{ __('kegiatan_lapangan.keterangan.menunggu_kelompok') }}</x-alert>
                    @else
                        <dl class="grid gap-3 text-[13px] sm:grid-cols-2">
                            <div><dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.kolom.kelompok') }}</dt><dd class="font-medium">{{ $kelompok->nama }}</dd></div>
                            <div><dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.kolom.lokasi') }}</dt><dd>{{ $kelompok->lokasi?->wilayah() ?? '—' }}</dd></div>
                            <div><dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.field.dosen_pembimbing_id') }}</dt><dd>{{ $kelompok->pembimbing?->namaLengkap() ?? '—' }}</dd></div>
                            <div><dt class="text-[11px] uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.field.pembimbing_mitra_nama') }}</dt><dd>{{ $kelompok->pembimbing_mitra_nama ?? '—' }}</dd></div>
                        </dl>
                        <h3 class="mt-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('kegiatan_lapangan.kartu.anggota') }}</h3>
                        <ul class="mt-1 grid gap-1 text-[12.5px] sm:grid-cols-2">
                            @foreach ($kelompok->anggota->sortBy(fn ($x) => $x->mahasiswa->nim) as $x)
                                <li>{{ $x->mahasiswa->nama }} <span class="tabular text-ink-faint">· {{ $x->mahasiswa->prodi->nama }}</span>
                                    @if ($x->is_ketua_kelompok)
                                        <x-chip tone="info">{{ __('kegiatan_lapangan.keterangan.ketua') }}</x-chip>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <x-card flush :title="__('kegiatan_lapangan.kartu.logbook')">
                    @if ($a->logbook->isEmpty())
                        <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('kegiatan_lapangan.keterangan.logbook_kosong') }}</p>
                    @else
                        <ul class="divide-y divide-line/60 text-[13px]">
                            @foreach ($a->logbook as $l)
                                <li class="px-5 py-3">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <div><span class="font-medium">{{ $l->judul }}</span> <span class="text-[11.5px] text-ink-faint">· {{ $l->jenis->label() }}</span></div>
                                        <span class="tabular text-[11.5px] text-ink-muted">{{ Format::tanggal($l->tanggal) }}</span>
                                    </div>
                                    @if ($l->uraian)
                                        <p class="mt-1 whitespace-pre-line text-[12.5px] leading-relaxed">{{ $l->uraian }}</p>
                                    @endif
                                    @if ($l->berkas_path)
                                        <a href="{{ route('berkas.logbook-kegiatan', $l) }}" class="mt-1 inline-block text-[12px] text-navy hover:underline">{{ $l->berkas_nama ?? __('kegiatan_lapangan.keterangan.lampiran') }}</a>
                                    @endif
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        @if ($l->sudahDiperiksa())
                                            <x-chip tone="success">{{ __('kegiatan_lapangan.keterangan.diperiksa') }}</x-chip>
                                            @if ($l->catatan_pembimbing)
                                                <span class="text-[12px] text-ink-muted">{{ $l->catatan_pembimbing }}</span>
                                            @endif
                                        @else
                                            <x-chip tone="warning">{{ __('kegiatan_lapangan.keterangan.belum_diperiksa') }}</x-chip>
                                            <form method="POST" action="{{ route('mahasiswa.kegiatan-lapangan.logbook.hapus', $l) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[12px] text-danger hover:underline">{{ __('kegiatan_lapangan.aksi.hapus') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>

            <div class="space-y-5">
                <x-card :title="__('kegiatan_lapangan.kartu.nilai')">
                    <dl class="space-y-1.5 text-[13px]">
                        <div class="flex justify-between"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.kolom.nilai_akhir') }}</dt><dd class="tabular font-medium">{{ $a->nilai_akhir !== null ? Format::angka((float) $a->nilai_akhir) : '—' }}</dd></div>
                        @if ($a->krsKonversi?->nilai)
                            <div class="flex justify-between"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.keterangan.huruf') }}</dt><dd class="font-medium">{{ $a->krsKonversi->nilai->nilai_huruf?->value }}</dd></div>
                            <div class="flex justify-between"><dt class="text-ink-muted">{{ __('kegiatan_lapangan.keterangan.sks_diakui') }}</dt><dd class="tabular">{{ Format::bulat($a->sks_konversi) }}</dd></div>
                        @endif
                    </dl>
                </x-card>

                @if ($kelompok !== null && $a->krsKonversi === null)
                    <x-card :title="__('kegiatan_lapangan.kartu.tulis_logbook')">
                        <form method="POST" action="{{ route('mahasiswa.kegiatan-lapangan.logbook', $a) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <x-field :label="__('kegiatan_lapangan.field.jenis_logbook')" name="jenis" :options="$jenisLogbook" required />
                            <x-field :label="__('kegiatan_lapangan.field.tanggal')" name="tanggal" type="date" :value="now()->toDateString()" required />
                            <x-field :label="__('kegiatan_lapangan.field.judul')" name="judul" required />
                            <x-field :label="__('kegiatan_lapangan.field.uraian')" name="uraian" type="textarea" />
                            <div>
                                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted" for="berkas-{{ $a->uuid }}">{{ __('kegiatan_lapangan.field.berkas') }}</label>
                                <input id="berkas-{{ $a->uuid }}" type="file" name="berkas" class="block w-full text-[12.5px]">
                            </div>
                            <x-button type="submit" size="sm">{{ __('kegiatan_lapangan.aksi.simpan') }}</x-button>
                        </form>
                    </x-card>
                @endif
            </div>
        </div>
    @empty
        @if ($terbuka->isEmpty())
            <x-empty-state :title="__('kegiatan_lapangan.keterangan.belum_ikut')" :description="__('kegiatan_lapangan.keterangan.belum_ikut_deskripsi')" />
        @endif
    @endforelse
@endsection
