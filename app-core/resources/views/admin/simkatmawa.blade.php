@extends('layouts.app')

@section('title', __('simkatmawa.judul'))

@section('content')
    @php
        $baris = $tabel[$kategoriAktif->value];
        $siap = $baris->filter->siap()->count();
        $kolom = \App\Services\Kemahasiswaan\EksporSimkatmawa::KOLOM[$kategoriAktif->value];
    @endphp

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="kategori" value="{{ $kategoriAktif->value }}">
            <x-field :label="__('simkatmawa.filter.dari')" name="dari" type="date" :value="$filter->dari->toDateString()" />
            <x-field :label="__('simkatmawa.filter.sampai')" name="sampai" type="date" :value="$filter->sampai->toDateString()" />
            <label class="min-w-[220px]">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('simkatmawa.filter.prodi') }}</span>
                <select name="prodi" class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                    <option value="">{{ __('simkatmawa.filter.semua_prodi') }}</option>
                    @foreach ($daftarProdi as $p)
                        <option value="{{ $p->id }}" @selected($filter->prodiId === $p->id)>{{ $p->namaLengkap() }}</option>
                    @endforeach
                </select>
            </label>
            <x-button type="submit">{{ __('simkatmawa.filter.terapkan') }}</x-button>
        </form>
    </x-card>

    <x-alert tone="info" class="mb-5">{{ __('simkatmawa.pengantar') }}</x-alert>

    @if ($menunggu > 0)
        <x-alert tone="warning" class="mb-5">{{ __('simkatmawa.ringkas.menunggu', ['jumlah' => $menunggu]) }}</x-alert>
    @endif

    @if ($tanpaKategori > 0)
        <x-alert tone="warning" class="mb-5">{{ __('simkatmawa.ringkas.tanpa_kategori', ['jumlah' => $tanpaKategori]) }}</x-alert>
    @endif

    {{-- Satu tabel per kategori SIMKATMAWA. Tautan, bukan tab Alpine: filter
         dan tabel aktif ikut terbawa saat halaman dimuat ulang. --}}
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('simkatmawa.judul') }}">
        @foreach ($tabel as $kode => $isi)
            @php $k = \App\Enums\KategoriSimkatmawa::from($kode); @endphp
            <a href="{{ route('admin.simkatmawa', [...$filter->query(), 'kategori' => $kode]) }}"
                @class([
                    'rounded-control border px-3.5 py-2 text-[13px] font-semibold',
                    'border-navy bg-navy text-canvas' => $k === $kategoriAktif,
                    'border-line bg-surface text-navy hover:border-navy' => $k !== $kategoriAktif,
                ])
                @if ($k === $kategoriAktif) aria-current="page" @endif>
                {{ $k->label() }}
                <span class="tabular ml-1 opacity-75">{{ $isi->count() }}</span>
            </a>
        @endforeach
    </nav>

    <x-card :title="$kategoriAktif->label()" flush>
        <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
            <x-chip tone="success">{{ __('simkatmawa.ringkas.siap', ['jumlah' => $siap]) }}</x-chip>
            <x-chip tone="danger">{{ __('simkatmawa.ringkas.tidak_lengkap', ['jumlah' => $baris->count() - $siap]) }}</x-chip>
            <x-chip tone="warning">{{ __('simkatmawa.ringkas.peringatan', ['jumlah' => $baris->filter(fn ($b) => $b->peringatan !== [])->count()]) }}</x-chip>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <span class="text-[11.5px] text-ink-faint">{{ __('simkatmawa.unduh_catatan') }}</span>
                @foreach (\App\Support\EksporTabel::FORMAT as $format)
                    <x-button size="sm" variant="outline"
                        :href="route('admin.simkatmawa.unduh', [...$filter->query(), 'kategori' => $kategoriAktif->value, 'format' => $format])">
                        {{ __('simkatmawa.unduh_'.$format) }}
                    </x-button>
                @endforeach
            </div>
        </div>

        @if ($baris->isEmpty())
            <x-empty-state :title="__('simkatmawa.kosong')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-[12.5px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-4 py-2.5 font-semibold">{{ __('simkatmawa.status_siap') }}</th>
                            @foreach ($kolom as $k)
                                <th class="px-3 py-2.5 font-semibold">{{ __('simkatmawa.kolom.'.$k) }}</th>
                            @endforeach
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Baris yang akan ditolak lebih dulu: itulah yang perlu dikerjakan. --}}
                        @foreach ($baris->sortBy(fn ($b) => $b->siap() ? 1 : 0) as $b)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-poin="{{ $b->poin->uuid }}">
                                <td class="px-4 py-2.5">
                                    <x-chip :tone="$b->siap() ? 'success' : 'danger'">
                                        {{ $b->siap() ? __('simkatmawa.status_siap') : __('simkatmawa.status_galat') }}
                                    </x-chip>
                                    @if ($b->galat !== [] || $b->peringatan !== [])
                                        <ul class="mt-1.5 min-w-[220px] space-y-0.5 text-[11.5px]">
                                            @foreach ($b->galat as $g)
                                                <li class="text-danger">{{ $g }}</li>
                                            @endforeach
                                            @foreach ($b->peringatan as $w)
                                                <li class="text-warning">{{ $w }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                @foreach ($kolom as $k)
                                    <td @class(['px-3 py-2.5', 'tabular' => in_array($k, ['nim', 'prodi_kode', 'tanggal_mulai', 'tanggal_selesai', 'jumlah_pt', 'jumlah_negara', 'pembimbing_nidn', 'pembimbing_nuptk'], true)])>
                                        @if (str_starts_with($k, 'tautan_') && filled($b->nilai[$k]))
                                            <a href="{{ $b->nilai[$k] }}" target="_blank" rel="noopener noreferrer" class="text-navy underline">↗</a>
                                        @else
                                            {{ $b->nilai[$k] ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-2.5 text-right">
                                    <x-button size="sm" variant="outline" :href="route('admin.poin-kemahasiswaan.simkatmawa', $b->poin)">
                                        {{ __('simkatmawa.tombol_lengkapi') }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
