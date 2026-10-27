@php
    use App\Support\Format;

    $kueri = array_filter([
        'tampilan' => $tampilan === 'daftar' ? 'daftar' : null,
        'sasaran' => $filter->sasaran?->value,
        'prodi' => $sasaranPilihan === [] ? null : $filter->prodiId,
        'jenis' => $filter->jenis?->value,
    ]);
    $rute = $prefix.'.kalender';
@endphp
@extends('layouts.app')

@section('title', __('kalender.judul'))

@section('aksi')
    @if ($bolehKelola)
        <x-button href="{{ route('admin.kalender.peristiwa.index', ['tahun' => $bulan->year]) }}" variant="outline" size="sm">
            {{ __('kalender.aksi.kelola') }}
        </x-button>
        <x-button href="{{ route('admin.kalender.peristiwa.create') }}" size="sm">
            + {{ __('kalender.aksi.baru') }}
        </x-button>
    @endif
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    {{-- Ditampilkan sekali dari flash, tidak pernah disimpan: yang ada di basis
         data hanya hash-nya. --}}
    @if (session('tautan_kalender'))
        <div class="mb-5 rounded-card border-2 border-gold bg-gold/10 px-5 py-4" x-data="{ tersalin: false }">
            <div class="mb-2 text-[13px] font-bold text-navy">{{ __('kalender.langganan.baru') }}</div>
            <div class="flex flex-wrap items-center gap-2">
                <code x-ref="tautan" class="tabular min-w-0 flex-1 select-all break-all rounded border border-gold/50 bg-surface px-2 py-1.5 text-[12px]">{{ session('tautan_kalender') }}</code>
                <x-button size="sm" variant="outline"
                    x-on:click="navigator.clipboard.writeText($refs.tautan.textContent.trim()); tersalin = true">
                    <span x-text="tersalin ? @js(__('kalender.aksi.tersalin')) : @js(__('kalender.aksi.salin'))">{{ __('kalender.aksi.salin') }}</span>
                </x-button>
            </div>
            <p class="mt-2.5 text-[12px] leading-relaxed text-ink-muted">{{ __('kalender.langganan.baru_petunjuk') }}</p>
        </div>
    @endif

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
            <div class="min-w-0">
                <label for="kalender-bulan" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('kalender.field.bulan') }}</label>
                <input id="kalender-bulan" type="month" name="bulan" value="{{ $bulan->format('Y-m') }}"
                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10">
            </div>
            <x-field :label="__('kalender.field.tampilan')" name="tampilan" :options="['bulan' => __('kalender.tampilan.bulan'), 'daftar' => __('kalender.tampilan.daftar')]" :value="$tampilan" required />
            <x-field :label="__('kalender.field.jenis')" name="jenis" :options="$jenisPilihan" :value="$filter->jenis?->value" />
            @if ($sasaranPilihan !== [])
                <x-field :label="__('kalender.field.sasaran')" name="sasaran" :options="$sasaranPilihan" :value="$filter->sasaran?->value" />
                <x-field :label="__('kalender.field.prodi')" name="prodi" :options="$prodiPilihan" :value="$filter->prodiId" />
            @endif
            <div class="flex items-end"><x-button type="submit">{{ __('kalender.aksi.tampilkan') }}</x-button></div>
        </form>
    </x-card>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-serif text-xl font-semibold">{{ Format::bulanTahun($bulan) }}</h2>
        <div class="flex gap-2">
            <x-button href="{{ route($rute, ['bulan' => $bulan->copy()->subMonth()->format('Y-m')] + $kueri) }}" variant="outline" size="sm">{{ __('kalender.aksi.sebelumnya') }}</x-button>
            <x-button href="{{ route($rute, $kueri) }}" variant="ghost" size="sm">{{ __('kalender.aksi.bulan_ini') }}</x-button>
            <x-button href="{{ route($rute, ['bulan' => $bulan->copy()->addMonth()->format('Y-m')] + $kueri) }}" variant="outline" size="sm">{{ __('kalender.aksi.berikutnya') }}</x-button>
        </div>
    </div>

    @if ($tampilan === 'bulan')
        <x-card flush class="mb-5">
            <div class="overflow-x-auto">
                <div class="min-w-[760px]">
                    <div class="grid grid-cols-7 border-b border-line text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        @foreach (__('kalender.hari') as $hari)
                            <div class="px-2.5 py-2">{{ $hari }}</div>
                        @endforeach
                    </div>
                    @foreach ($grid as $minggu)
                        <div class="grid grid-cols-7 border-b border-line/60 last:border-b-0">
                            @foreach ($minggu as $hari)
                                <div @class([
                                    'min-h-[104px] border-r border-line/60 px-2 py-1.5 last:border-r-0',
                                    'bg-danger-bg/40' => $hari['libur'],
                                    'bg-zebra' => !$hari['libur'] && !$hari['diBulan'],
                                ])>
                                    <div @class([
                                        'tabular mb-1 text-[12px]',
                                        'text-ink-faint' => !$hari['diBulan'],
                                        'font-bold text-navy' => $hari['hariIni'],
                                        'text-danger' => $hari['libur'] && !$hari['hariIni'],
                                    ])>
                                        @if ($hari['hariIni'])
                                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-navy px-1 text-canvas">{{ $hari['tanggal']->day }}</span>
                                        @else
                                            {{ $hari['tanggal']->day }}
                                        @endif
                                    </div>
                                    <ul class="space-y-1">
                                        @foreach ($hari['peristiwa']->take(3) as $p)
                                            <li class="truncate rounded border px-1.5 py-0.5 text-[11px] leading-snug
                                                {{ match ($p->jenis->tone()) {
                                                    'danger' => 'border-danger-line bg-danger-bg text-danger',
                                                    'warning' => 'border-warning-line bg-warning-bg text-warning',
                                                    'info' => 'border-info-line bg-info-bg text-info',
                                                    'success' => 'border-success-line bg-success-bg text-success',
                                                    'gold' => 'border-gold/40 bg-gold/15 text-warning',
                                                    default => 'border-line bg-line/40 text-ink-muted',
                                                } }}
                                                {{ $p->draf() ? 'border-dashed opacity-70' : '' }}"
                                                title="{{ $p->judul }}{{ $p->prodiNama ? ' · '.$p->prodiNama : '' }} · {{ $p->rentang() }}">
                                                @if ($p->berwaktu())<span class="tabular">{{ Format::jam($p->jamMulai) }}</span>@endif
                                                {{ $p->judul }}
                                            </li>
                                        @endforeach
                                        @if ($hari['peristiwa']->count() > 3)
                                            <li class="text-[11px] text-ink-faint">{{ __('kalender.keterangan.lainnya', ['jumlah' => $hari['peristiwa']->count() - 3]) }}</li>
                                        @endif
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </x-card>
    @endif

    <x-card flush class="mb-5" :title="__('kalender.tampilan.daftar')" :meta="__('kalender.petunjuk.turunan')">
        @if ($daftar->isEmpty())
            <div class="p-5">
                <x-empty-state :title="__('kalender.keterangan.kosong')" :description="__('kalender.keterangan.kosong_deskripsi')" />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.tanggal') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.peristiwa') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.jenis') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.sasaran') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.sumber') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular whitespace-nowrap px-5 py-3">{{ $p->rentang() }}</td>
                                <td class="px-5 py-3">
                                    <div class="font-medium">
                                        {{ $p->judul }}
                                        @if ($p->draf())
                                            <x-chip>{{ $p->status->label() }}</x-chip>
                                        @endif
                                        @if ($p->pribadi)
                                            <x-chip tone="gold">{{ __('kalender.keterangan.pribadi') }}</x-chip>
                                        @endif
                                    </div>
                                    <div class="text-[11.5px] text-ink-faint">
                                        {{ $p->prodiNama ?? __('kalender.keterangan.semua_prodi') }}@if ($p->keterangan) · {{ $p->keterangan }}@endif @if ($p->lokasi) · {{ $p->lokasi }}@endif
                                    </div>
                                </td>
                                <td class="px-5 py-3"><x-chip :tone="$p->jenis->tone()">{{ $p->jenis->label() }}</x-chip></td>
                                <td class="px-5 py-3 text-[12.5px]">{{ $p->sasaran->label() }}</td>
                                <td class="px-5 py-3 text-[12.5px] text-ink-muted">
                                    @if ($p->manual() && $bolehKelola)
                                        <a href="{{ route('admin.kalender.peristiwa.edit', $p->uuid) }}" class="font-medium text-navy hover:underline">{{ __('kalender.aksi.ubah') }}</a>
                                    @else
                                        {{ $p->jenis->sumber() }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @can('subscribe', \App\Models\Akademik\KalenderPeristiwa::class)
        <x-card :title="__('kalender.langganan.judul')">
            <p class="mb-3 max-w-3xl text-[13px] leading-relaxed text-ink-muted">{{ __('kalender.langganan.deskripsi') }}</p>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-[12.5px]">
                    @if ($langganan)
                        <div>{{ __('kalender.langganan.aktif', ['tanggal' => Format::tanggal($langganan->created_at)]) }}</div>
                        <div class="text-ink-faint">
                            {{ $langganan->last_used_at
                                ? __('kalender.langganan.dipakai', ['tanggal' => Format::tanggal($langganan->last_used_at).' '.Format::jam($langganan->last_used_at)])
                                : __('kalender.langganan.belum_dipakai') }}
                        </div>
                    @else
                        <div class="text-ink-faint">{{ __('kalender.langganan.tidak_ada') }}</div>
                    @endif
                </div>

                <div class="flex gap-2">
                    <form method="POST" action="{{ route($prefix.'.kalender.langganan') }}">
                        @csrf
                        <x-button type="submit" size="sm" :variant="$langganan ? 'outline' : 'primary'">
                            {{ $langganan ? __('kalender.aksi.buat_ulang') : __('kalender.aksi.buat_tautan') }}
                        </x-button>
                    </form>
                    @if ($langganan)
                        <form method="POST" action="{{ route($prefix.'.kalender.langganan.cabut') }}"
                            data-confirm="{{ __('kalender.keterangan.konfirmasi_cabut') }}">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" size="sm" variant="danger">{{ __('kalender.aksi.cabut') }}</x-button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>
    @endcan
@endsection
