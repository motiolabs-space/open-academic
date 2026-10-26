@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('jadwal_ujian.judul'))

@section('aksi')
    @can('create', \App\Models\Akademik\JadwalUjian::class)
        <x-button href="{{ route('admin.jadwal-ujian.susun', ['term' => $term->id]) }}" variant="outline" size="sm">
            {{ __('jadwal_ujian.aksi.susun') }}
        </x-button>
        <x-button href="{{ route('admin.jadwal-ujian.create', ['term' => $term->id]) }}" size="sm">
            + {{ __('jadwal_ujian.aksi.baru') }}
        </x-button>
    @endcan
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-[repeat(3,minmax(0,1fr))_auto]">
            <x-field :label="__('jadwal_ujian.field.term')" name="term" :options="$daftarTerm" :value="$term->id" required />
            <x-field :label="__('jadwal_ujian.field.jenis')" name="jenis" :options="$jenisPilihan" :value="$jenis?->value" />
            <x-field :label="__('jadwal_ujian.field.prodi')" name="prodi" :options="$daftarProdi" :value="$prodiId" />
            <div class="flex items-end"><x-button type="submit">{{ __('jadwal_ujian.aksi.tampilkan') }}</x-button></div>
        </form>
    </x-card>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('jadwal_ujian.kartu.ringkasan_draf')" :value="Format::bulat($jumlahDraf)" />
        <x-stat-card :label="__('jadwal_ujian.kartu.ringkasan_terbit')" :value="Format::bulat($daftar->count() - $jumlahDraf)" />
        <x-stat-card :label="__('jadwal_ujian.kartu.ringkasan_bentrok')" :value="Format::bulat($jumlahPenghalang)" />
        <x-stat-card :label="__('jadwal_ujian.kartu.ringkasan_tanpa_jadwal')" :value="$tanpaJadwal === null ? '—' : Format::bulat($tanpaJadwal)" />
    </div>

    {{-- Bentrok lebih dulu daripada daftarnya: jadwal yang menempatkan satu
         mahasiswa di dua ruang tampak persis sama dengan jadwal yang benar. --}}
    @if ($bentrok !== [])
        <x-card flush class="mb-5" :title="__('jadwal_ujian.kartu.bentrok')">
            <ul class="divide-y divide-line/60 text-[13px]">
                @foreach ($daftar->whereIn('id', array_keys($bentrok)) as $j)
                    <li class="px-5 py-3">
                        <a href="{{ route('admin.jadwal-ujian.show', $j) }}" class="font-medium text-navy hover:underline">
                            {{ $j->jenis->singkatan() }} · {{ $j->kelasKuliah->namaLengkap() }}
                        </a>
                        <span class="tabular text-[11.5px] text-ink-faint">· {{ $j->rentangWaktu() }}</span>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($bentrok[$j->id] as $b)
                                <li class="{{ $b->menghalangi ? 'text-danger' : 'text-warning' }}">• {{ $b->pesan }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card flush :title="__('jadwal_ujian.kartu.daftar')" :meta="__('jadwal_ujian.petunjuk.draf')">
        @if ($daftar->isEmpty())
            <x-empty-state :title="__('jadwal_ujian.keterangan.kosong')" :description="__('jadwal_ujian.keterangan.kosong_deskripsi')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.kelas') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.jenis') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.waktu') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.ruang') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.pengawas') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.status') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $j)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $j->kelasKuliah->mataKuliah->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">
                                        {{ $j->kelasKuliah->mataKuliah->kode }} · {{ $j->kelasKuliah->namaLengkap() }} · {{ $j->kelasKuliah->prodi?->nama }}
                                    </div>
                                </td>
                                <td class="px-5 py-3"><x-chip>{{ $j->jenis->singkatan() }}</x-chip></td>
                                <td class="tabular px-5 py-3">
                                    {{ Format::tanggalHari($j->tanggal) }}
                                    <div class="text-[11.5px] text-ink-faint">{{ Format::rentangJam($j->jam_mulai, $j->jam_selesai) }}</div>
                                </td>
                                <td class="tabular px-5 py-3">
                                    {{ $j->ruang->map(fn ($r) => $r->ruang?->kode)->filter()->implode(', ') ?: '—' }}
                                    <div class="text-[11.5px] text-ink-faint">{{ Format::bulat($j->totalKursi()) }} {{ strtolower(__('jadwal_ujian.kolom.kursi')) }}</div>
                                </td>
                                <td class="px-5 py-3 text-[12.5px]">
                                    {{ $j->pengawas->map->namaOrang()->implode(', ') ?: '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$j->status->tone()">{{ $j->status->label() }}</x-chip>
                                    @if (isset($bentrok[$j->id]) && $bentrok[$j->id]->contains('menghalangi', true))
                                        <x-chip tone="danger">{{ __('jadwal_ujian.kolom.bentrok') }}</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-button href="{{ route('admin.jadwal-ujian.show', $j) }}" variant="outline" size="sm">
                                        {{ __('jadwal_ujian.aksi.buka') }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Terbit per jenis: satu pengumuman per jenis ujian, bukan UTS dan
                 UAS bercampur dalam satu pemberitahuan. --}}
            @can('publish', \App\Models\Akademik\JadwalUjian::class)
                @if ($jenis !== null && $jumlahDraf > 0)
                    <form method="POST" action="{{ route('admin.jadwal-ujian.terbitkan') }}"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3">
                        @csrf
                        <input type="hidden" name="tahun_akademik_id" value="{{ $term->id }}">
                        <input type="hidden" name="jenis" value="{{ $jenis->value }}">
                        @if ($prodiId)
                            <input type="hidden" name="prodi_id" value="{{ $prodiId }}">
                        @endif
                        <p class="text-[12.5px] text-ink-muted">{{ __('jadwal_ujian.petunjuk.draf') }}</p>
                        <x-button type="submit" size="sm">{{ __('jadwal_ujian.aksi.terbitkan', ['jumlah' => $jumlahDraf]) }}</x-button>
                    </form>
                @endif
            @endcan
        @endif
    </x-card>
@endsection
