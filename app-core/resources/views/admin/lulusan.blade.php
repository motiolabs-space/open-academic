@extends('layouts.app')

@section('title', __('lulusan.judul'))

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <x-alert tone="info" class="mb-5">{{ __('lulusan.pengantar') }}</x-alert>

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label>
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('lulusan.filter.tahun_lulus') }}</span>
                <select name="tahun_lulus" class="rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                    <option value="">{{ __('lulusan.filter.semua_tahun') }}</option>
                    @foreach ($daftarTahun as $t)
                        <option value="{{ $t }}" @selected($filter->tahunLulus === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </label>
            <label class="min-w-[220px]">
                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('lulusan.filter.prodi') }}</span>
                <select name="prodi" class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy">
                    <option value="">{{ __('lulusan.filter.semua_prodi') }}</option>
                    @foreach ($daftarProdi as $p)
                        <option value="{{ $p->id }}" @selected($filter->prodiId === $p->id)>{{ $p->namaLengkap() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-center gap-2 py-2 text-[13px]">
                <input type="checkbox" name="hanya_perlu" value="1" class="accent-navy" @checked($filter->hanyaPerluVerifikasi)>
                {{ __('lulusan.filter.hanya_perlu') }}
            </label>
            <x-button type="submit">{{ __('lulusan.filter.terapkan') }}</x-button>
        </form>
    </x-card>

    <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
        <x-card :title="__('lulusan.judul')" flush>
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
                <x-chip>{{ __('lulusan.ringkas.lulusan', ['jumlah' => $daftar->count()]) }}</x-chip>
                <x-chip tone="success">{{ __('lulusan.ringkas.lengkap', ['jumlah' => $daftar->where('statusKontak', \App\DTOs\Kemahasiswaan\BarisLulusan::LENGKAP)->count()]) }}</x-chip>
                <x-chip tone="warning">{{ __('lulusan.ringkas.perlu', ['jumlah' => $daftar->filter->perluVerifikasi()->count()]) }}</x-chip>
                <x-chip tone="info">{{ __('lulusan.ringkas.ditelusuri', ['jumlah' => $daftar->filter(fn ($b) => $b->alumni->status_pekerjaan?->ditelusuri())->count()]) }}</x-chip>
            </div>

            @unless ($bolehKelola)
                <p class="border-b border-line px-5 py-2 text-[12px] text-ink-muted">{{ __('lulusan.tersamar') }}</p>
            @endunless

            @if ($daftar->isEmpty())
                <x-empty-state :title="__('lulusan.kosong')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-[12.5px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-4 py-2.5 font-semibold">{{ __('lulusan.kolom.nim') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('lulusan.kolom.nama') }}</th>
                                <th class="px-3 py-2.5 text-center font-semibold">{{ __('lulusan.kolom.tahun_masuk') }}</th>
                                <th class="px-3 py-2.5 text-center font-semibold">{{ __('lulusan.kolom.tahun_lulus') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('lulusan.kolom.kontak') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('lulusan.kolom.status_pekerjaan') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $b)
                                @php($a = $b->alumni)
                                <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-alumni="{{ $a->uuid }}">
                                    <td class="tabular px-4 py-2.5">{{ $a->mahasiswa->nim }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-medium">{{ $a->mahasiswa->nama }}</div>
                                        <div class="text-[11.5px] text-ink-faint">{{ $a->mahasiswa->prodi?->namaLengkap() }}</div>
                                    </td>
                                    <td class="tabular px-3 py-2.5 text-center">{{ $a->mahasiswa->angkatan }}</td>
                                    <td class="tabular px-3 py-2.5 text-center">{{ $a->tahun_lulus }}</td>
                                    <td class="px-3 py-2.5">
                                        <x-chip :tone="match ($b->statusKontak) { 'lengkap' => 'success', 'kosong' => 'danger', default => 'warning' }">
                                            {{ __('lulusan.kontak_status.'.$b->statusKontak) }}
                                        </x-chip>

                                        @if ($bolehKelola)
                                            <div class="mt-1 text-[11.5px] text-ink-muted">
                                                {{ $a->email_pribadi ?? '—' }} · <span class="tabular">{{ $a->telepon ?? '—' }}</span>
                                            </div>
                                        @endif

                                        @if ($b->tanda !== [])
                                            <ul class="mt-1 space-y-0.5 text-[11.5px] text-warning">
                                                @foreach ($b->tanda as $t)
                                                    <li>{{ __('lulusan.tanda.'.$t, ['bulan' => config('kemahasiswaan.lulusan.verifikasi_berlaku_bulan')]) }}</li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if ($a->kontak_diverifikasi_at)
                                            <div class="tabular mt-1 text-[11px] text-ink-faint">
                                                {{ __('lulusan.verifikasi.terakhir', ['tanggal' => \App\Support\Format::tanggal($a->kontak_diverifikasi_at)]) }}
                                            </div>
                                        @endif

                                        @if ($bolehKelola)
                                            {{-- <details>, bukan Alpine: formulir ini perlu bekerja tanpa skrip. --}}
                                            <details class="mt-2">
                                                <summary class="cursor-pointer text-[11.5px] font-semibold text-navy">{{ __('lulusan.verifikasi.tombol') }}</summary>
                                                <form method="POST" action="{{ route('admin.lulusan.kontak', $a) }}" class="mt-2 grid gap-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <x-field :label="__('lulusan.verifikasi.surel')" name="email_pribadi" type="email" :value="$a->email_pribadi" />
                                                    <x-field :label="__('lulusan.verifikasi.telepon')" name="telepon" :value="$a->telepon" />
                                                    <label class="flex items-center gap-2 text-[12px]">
                                                        <input type="hidden" name="kontak_tidak_dibagikan" value="0">
                                                        <input type="checkbox" name="kontak_tidak_dibagikan" value="1" class="accent-navy" @checked($a->kontak_tidak_dibagikan)>
                                                        {{ __('lulusan.verifikasi.tidak_dibagikan') }}
                                                    </label>
                                                    <x-button type="submit" size="sm">{{ __('lulusan.verifikasi.simpan') }}</x-button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($a->status_pekerjaan)
                                            <x-chip :tone="$a->status_pekerjaan->tone()">{{ $a->status_pekerjaan->label() }}</x-chip>
                                        @endif
                                        @if ($a->instansi)
                                            <div class="mt-1 text-[11.5px]">{{ $a->instansi }}</div>
                                        @endif
                                        @if ($a->status_pekerjaan_sumber)
                                            <div class="mt-0.5 text-[11px] text-ink-faint">
                                                {{ $a->status_pekerjaan_sumber->label() }}@if ($a->status_pekerjaan_per) · <span class="tabular">{{ \App\Support\Format::tanggal($a->status_pekerjaan_per) }}</span>@endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <div class="flex flex-col gap-5">
            @if ($bolehEkspor)
                <x-card :title="__('lulusan.ekspor.judul')">
                    <p class="mb-3 text-[12px] leading-relaxed text-ink-muted">{{ __('lulusan.ekspor.penjelasan') }}</p>
                    <form method="POST" action="{{ route('admin.lulusan.ekspor') }}" class="grid gap-3"
                        data-confirm="{{ __('lulusan.ekspor.konfirmasi') }}">
                        @csrf
                        @foreach ($filter->query() as $kunci => $nilai)
                            @if ($kunci !== 'hanya_perlu')
                                <input type="hidden" name="{{ $kunci }}" value="{{ $nilai }}">
                            @endif
                        @endforeach
                        <x-field :label="__('lulusan.ekspor.keperluan')" name="keperluan" required :placeholder="__('lulusan.ekspor.keperluan_placeholder')" />
                        <x-field :label="__('lulusan.ekspor.format')" name="format" required
                            :options="collect($formatOptions)->mapWithKeys(fn ($f) => [$f => strtoupper($f)])->all()" value="xlsx" />
                        <x-button type="submit">{{ __('lulusan.ekspor.tombol') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if ($bolehImpor)
                <x-card :title="__('lulusan.impor.judul')">
                    <p class="mb-3 text-[12px] leading-relaxed text-ink-muted">{{ __('lulusan.impor.penjelasan') }}</p>

                    @if ($pratinjau)
                        <div class="mb-4 rounded border border-line p-3" data-pratinjau-impor>
                            <div class="mb-2 text-[12.5px] font-semibold">{{ __('lulusan.impor.pratinjau') }} · {{ $pratinjau->sumber->label() }}</div>

                            @foreach ($pratinjau->galatBerkas as $g)
                                <x-alert tone="danger" class="mb-2">{{ $g }}</x-alert>
                            @endforeach

                            @if ($pratinjau->galatBerkas === [])
                                <div class="flex flex-wrap gap-2">
                                    <x-chip tone="success">{{ __('lulusan.impor.diterima', ['jumlah' => count($pratinjau->akanDiterapkan())]) }}</x-chip>
                                    <x-chip>{{ __('lulusan.impor.tidak_berubah', ['jumlah' => count($pratinjau->tidakBerubah())]) }}</x-chip>
                                    <x-chip tone="danger">{{ __('lulusan.impor.ditolak', ['jumlah' => count($pratinjau->ditolak())]) }}</x-chip>
                                </div>

                                @if ($pratinjau->ditolak() !== [])
                                    <table class="mt-3 w-full text-[12px]">
                                        <thead>
                                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                                <th class="py-1.5">{{ __('lulusan.impor.baris') }}</th>
                                                <th>{{ __('lulusan.kolom.nim') }}</th>
                                                <th>{{ __('lulusan.impor.alasan') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($pratinjau->ditolak() as $t)
                                                <tr class="border-b border-line/50 align-top">
                                                    <td class="tabular py-1.5">{{ $t->baris }}</td>
                                                    <td class="tabular">{{ $t->nim ?: '—' }}</td>
                                                    <td class="text-danger">{{ implode(' ', $t->alasan) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            @endif

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ($pratinjau->dapatDiterapkan())
                                    <form method="POST" action="{{ route('admin.lulusan.impor.terapkan') }}" data-confirm="{{ __('lulusan.impor.konfirmasi') }}">
                                        @csrf
                                        <x-button type="submit" size="sm">{{ __('lulusan.impor.terapkan', ['jumlah' => count($pratinjau->akanDiterapkan())]) }}</x-button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.lulusan.impor.batal') }}">
                                    @csrf
                                    <x-button type="submit" size="sm" variant="outline">{{ __('lulusan.impor.batal') }}</x-button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.lulusan.impor') }}" enctype="multipart/form-data" class="grid gap-3">
                        @csrf
                        <label>
                            <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('lulusan.impor.berkas') }}</span>
                            <input type="file" name="berkas" accept=".csv,text/csv" required class="w-full text-[12.5px]">
                        </label>
                        <x-field :label="__('lulusan.impor.sumber')" name="sumber" required :options="$sumberOptions" />
                        <x-button type="submit" variant="outline">{{ __('lulusan.impor.periksa') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
@endsection
