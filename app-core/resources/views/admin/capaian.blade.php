@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('capaian.rekap.prodi_judul'))

@section('aksi')
    @if ($hasil !== null && $hasil['mahasiswa']->isNotEmpty())
        <x-button variant="outline" :href="route('admin.capaian.ekspor', array_filter(['prodi' => $prodi->uuid, 'angkatan' => $angkatan]))">
            {{ __('capaian.rekap.ekspor') }}
        </x-button>
    @endif
@endsection

@section('content')
    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1 text-[12px] font-medium text-ink-muted">
                {{ __('capaian.kelola.filter_prodi') }}
                <select name="prodi" class="min-w-[220px] rounded-control border border-line-input bg-surface px-2.5 py-2 text-[13px] text-ink outline-none focus:border-navy">
                    @foreach ($daftarProdi as $p)
                        <option value="{{ $p->uuid }}" @selected($prodi?->is($p))>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </label>

            <label class="flex flex-col gap-1 text-[12px] font-medium text-ink-muted">
                {{ __('capaian.rekap.angkatan') }}
                <select name="angkatan" class="min-w-[160px] rounded-control border border-line-input bg-surface px-2.5 py-2 text-[13px] text-ink outline-none focus:border-navy">
                    <option value="">{{ __('capaian.rekap.semua_angkatan') }}</option>
                    @foreach ($hasil['angkatan'] ?? [] as $a)
                        <option value="{{ $a }}" @selected($angkatan === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </label>

            <x-button type="submit" variant="outline">{{ __('capaian.kelola.tampilkan') }}</x-button>
        </form>
    </x-card>

    @if ($hasil === null || $hasil['mahasiswa']->isEmpty())
        <x-card>
            <x-empty-state :title="__('capaian.rekap.prodi_judul')" :description="__('capaian.rekap.prodi_kosong')" />
        </x-card>
    @else
        <x-card flush class="mb-5" :title="__('capaian.rekap.ringkasan')"
            :meta="__('capaian.istilah.ambang', ['ambang' => Format::angka($hasil['ambang'], 0)])">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('capaian.istilah.cpl') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ __('capaian.rekap.terukur') }}</th>
                            <th class="px-3 py-3 text-right font-semibold">{{ __('capaian.rekap.rerata') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('capaian.rekap.tercapai') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasil['cpl'] as $c)
                            @php $r = $hasil['ringkas'][$c->id] ?? ['jumlah' => 0, 'rerata' => null, 'tercapai' => 0]; @endphp
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-2.5">
                                    <span class="font-semibold">{{ $c->kode }}</span>
                                    <span class="text-ink-muted">{{ \Illuminate\Support\Str::limit($c->deskripsi, 90) }}</span>
                                </td>
                                <td class="tabular px-3 py-2.5 text-right">{{ Format::bulat($r['jumlah']) }}</td>
                                <td @class(['tabular px-3 py-2.5 text-right font-semibold', 'text-warning' => $r['rerata'] !== null && $r['rerata'] < $hasil['ambang']])>
                                    {{ Format::angka($r['rerata'], 1) }}
                                </td>
                                <td class="tabular px-5 py-2.5 text-right">
                                    {{ Format::bulat($r['tercapai']) }}
                                    <span class="text-ink-faint">({{ Format::angka($r['jumlah'] > 0 ? 100 * $r['tercapai'] / $r['jumlah'] : null, 0) }}%)</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card flush :title="__('capaian.rekap.per_mahasiswa')" :meta="Format::bulat($hasil['mahasiswa']->count()).' '.__('capaian.rekap.mahasiswa')">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                            <th class="px-4 py-3 font-semibold">{{ __('capaian.rekap.nim') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('capaian.rekap.nama') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ __('capaian.rekap.angkatan') }}</th>
                            @foreach ($hasil['cpl'] as $c)
                                <th class="px-2 py-3 text-center font-semibold normal-case" title="{{ $c->deskripsi }}">{{ $c->kode }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasil['mahasiswa'] as $baris)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-4 py-2">{{ $baris['mahasiswa']->nim }}</td>
                                <td class="px-4 py-2 font-medium">{{ $baris['mahasiswa']->nama }}</td>
                                <td class="tabular px-3 py-2 text-center">{{ $baris['mahasiswa']->angkatan }}</td>
                                @foreach ($hasil['cpl'] as $c)
                                    @php $n = $baris['cpl'][$c->id] ?? null; @endphp
                                    <td @class(['tabular px-2 py-2 text-center', 'font-semibold text-warning' => $n !== null && $n < $hasil['ambang']])>
                                        {{ Format::angka($n, 1) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <p class="mt-4 text-[12px] leading-relaxed text-ink-faint">
            {{ __('capaian.rekap.cara_baca') }} {{ __('capaian.rekap.bukan_dasbor') }}
        </p>
    @endif
@endsection
