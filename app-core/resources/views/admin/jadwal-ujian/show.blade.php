@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', $judul)

@section('aksi')
    @can('update', $jadwal)
        <x-button href="{{ route('admin.jadwal-ujian.edit', $jadwal) }}" variant="outline" size="sm">
            {{ __('jadwal_ujian.aksi.ubah') }}
        </x-button>
    @endcan
    @can('delete', $jadwal)
        <form method="POST" action="{{ route('admin.jadwal-ujian.destroy', $jadwal) }}">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="outline" size="sm">{{ __('jadwal_ujian.aksi.hapus') }}</x-button>
        </form>
    @endcan
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    <div class="mb-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <x-card>
            <dl class="grid grid-cols-[9rem_minmax(0,1fr)] gap-x-4 gap-y-2 text-[13px]">
                <dt class="text-ink-muted">{{ __('jadwal_ujian.kolom.jenis') }}</dt>
                <dd>{{ $jadwal->jenis->label() }}</dd>
                <dt class="text-ink-muted">{{ __('jadwal_ujian.kolom.kelas') }}</dt>
                <dd>{{ $jadwal->kelasKuliah->mataKuliah->kode }} · {{ $jadwal->kelasKuliah->namaLengkap() }}</dd>
                <dt class="text-ink-muted">{{ __('jadwal_ujian.kolom.waktu') }}</dt>
                <dd class="tabular">{{ $jadwal->rentangWaktu() }}</dd>
                <dt class="text-ink-muted">{{ __('jadwal_ujian.keterangan.pengampu') }}</dt>
                <dd>{{ $jadwal->kelasKuliah->dosen->map->namaLengkap()->implode(', ') ?: '—' }}</dd>
                <dt class="text-ink-muted">{{ __('jadwal_ujian.kolom.status') }}</dt>
                <dd><x-chip :tone="$jadwal->status->tone()">{{ $jadwal->status->label() }}</x-chip></dd>
                @if ($jadwal->catatan)
                    <dt class="text-ink-muted">{{ __('jadwal_ujian.field.catatan') }}</dt>
                    <dd>{{ $jadwal->catatan }}</dd>
                @endif
            </dl>
        </x-card>

        <x-card :title="__('jadwal_ujian.kolom.ruang')">
            <ul class="space-y-2 text-[13px]">
                @forelse ($jadwal->ruang as $r)
                    <li class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-medium">{{ $r->ruang?->kode }} · {{ $r->ruang?->nama }}</span>
                        <span class="tabular text-ink-muted">{{ Format::bulat($r->kursi) }} {{ strtolower(__('jadwal_ujian.kolom.kursi')) }}</span>
                        <span class="w-full text-[12px] text-ink-faint">
                            {{ __('jadwal_ujian.keterangan.pengawas_utama') }}:
                            {{ $jadwal->pengawas->where('jadwal_ujian_ruang_id', $r->id)->map->namaOrang()->implode(', ') ?: '—' }}
                        </span>
                    </li>
                @empty
                    <li class="text-ink-muted">—</li>
                @endforelse
            </ul>
            @php $tanpaRuang = $jadwal->pengawas->whereNull('jadwal_ujian_ruang_id'); @endphp
            @if ($tanpaRuang->isNotEmpty())
                <p class="mt-3 border-t border-line pt-3 text-[12px] text-ink-muted">
                    {{ __('jadwal_ujian.kolom.pengawas') }}: {{ $tanpaRuang->map->namaOrang()->implode(', ') }}
                </p>
            @endif
        </x-card>
    </div>

    <x-card class="mb-5" :title="__('jadwal_ujian.kartu.bentrok')">
        @if ($bentrok->isEmpty())
            <p class="text-[13px] text-ink-muted">{{ __('jadwal_ujian.keterangan.tanpa_bentrok') }}</p>
        @else
            <ul class="space-y-1 text-[13px]">
                @foreach ($bentrok as $b)
                    <li class="{{ $b->menghalangi ? 'text-danger' : 'text-warning' }}">• {{ $b->pesan }}</li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card flush :title="__('jadwal_ujian.kartu.peserta')" :meta="__('jadwal_ujian.petunjuk.peserta')">
        @if ($peserta->isEmpty())
            <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('jadwal_ujian.keterangan.tanpa_peserta') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.mahasiswa') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.ruang') }}</th>
                            @if ($jadwal->jenis->butuhKehadiran())
                                <th class="px-5 py-3 text-center font-semibold">{{ __('jadwal_ujian.kolom.kehadiran') }}</th>
                            @endif
                            @if ($tahanTunggakan)
                                <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.keuangan') }}</th>
                            @endif
                            <th class="px-5 py-3 font-semibold">{{ __('jadwal_ujian.kolom.kelayakan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($peserta as $p)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $p->mahasiswa->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $p->mahasiswa->nim }}</div>
                                </td>
                                <td class="tabular px-5 py-3">
                                    @if ($p->ruang)
                                        {{ $p->ruang->kode }}
                                    @else
                                        <x-chip tone="danger">{{ __('jadwal_ujian.keterangan.tanpa_kursi') }}</x-chip>
                                    @endif
                                </td>
                                @if ($jadwal->jenis->butuhKehadiran())
                                    <td class="tabular px-5 py-3 text-center">
                                        {{ $p->persenKehadiran === null ? '—' : Format::angka($p->persenKehadiran, 0).'%' }}
                                        @unless ($p->memenuhiKehadiran)
                                            <div><x-chip tone="warning">{{ __('jadwal_ujian.keterangan.di_bawah_ambang', ['minimum' => $minimum]) }}</x-chip></div>
                                        @endunless
                                    </td>
                                @endif
                                @if ($tahanTunggakan)
                                    <td class="px-5 py-3">
                                        @if ($p->tunggakan > 0)
                                            <x-chip tone="danger">{{ __('jadwal_ujian.keterangan.menunggak') }}</x-chip>
                                            <div class="tabular text-[11.5px] text-ink-faint">{{ Format::rupiah($p->tunggakan) }}</div>
                                        @else
                                            <x-chip tone="success">{{ __('jadwal_ujian.keterangan.lunas') }}</x-chip>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-5 py-3">
                                    @if ($p->layak)
                                        <x-chip tone="success">{{ __('jadwal_ujian.keterangan.layak') }}</x-chip>
                                    @else
                                        <x-chip tone="danger">{{ __('jadwal_ujian.keterangan.tidak_layak') }}</x-chip>
                                        <div class="mt-1 text-[11.5px] text-ink-muted">{{ implode(' ', $p->alasan) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
