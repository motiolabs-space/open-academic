@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('capaian.rekap.kelas_judul'))

@section('aksi')
    @if ($rekap['terhitung'])
        <x-button variant="outline" :href="route('dosen.capaian.ekspor', $kelas)">{{ __('capaian.rekap.ekspor') }}</x-button>
    @endif
@endsection

@section('content')
    @if (! $rekap['terhitung'])
        <x-card class="mb-5">
            <x-empty-state :title="__('capaian.rekap.kelas_judul')"
                :description="$kelas->status_nilai === 'final' ? __('capaian.rekap.kelas_tanpa') : __('capaian.rekap.kelas_belum')" />
        </x-card>

        @if ($cakupan['versi'] !== null && $cakupan['tak_terukur']->isNotEmpty())
            <x-alert tone="warning">
                {{ __('capaian.pemetaan.tak_terukur', [
                    'jumlah' => $cakupan['tak_terukur']->count(),
                    'daftar' => $cakupan['tak_terukur']->pluck('kode')->implode(', '),
                ]) }}
            </x-alert>
        @endif
    @else
        <div class="mb-5 grid gap-5 lg:grid-cols-2">
            <x-card :title="__('capaian.rekap.ringkasan_cpmk')" :meta="__('capaian.istilah.ambang', ['ambang' => Format::angka($rekap['ambang'], 0)])">
                <dl class="space-y-3">
                    @foreach ($rekap['cpmk'] as $c)
                        @php $r = $rekap['ringkas_cpmk'][$c->id] ?? null; @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-[13px]">
                                <dt class="min-w-0"><span class="font-semibold">{{ $c->kode }}</span> <span class="text-ink-muted">{{ $c->rumusan }}</span></dt>
                                <dd class="tabular shrink-0 font-semibold">{{ Format::angka($r['rerata'] ?? null, 1) }}</dd>
                            </div>
                            <p class="tabular mt-0.5 text-[11.5px] text-ink-faint">
                                {{ __('capaian.rekap.tercapai') }}: {{ $r['tercapai'] ?? 0 }} / {{ $r['jumlah'] ?? 0 }}
                            </p>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card :title="__('capaian.rekap.ringkasan')">
                <dl class="space-y-3">
                    @foreach ($rekap['cpl'] as $c)
                        @php $r = $rekap['ringkas_cpl'][$c->id] ?? null; @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-[13px]">
                                <dt class="min-w-0"><span class="font-semibold">{{ $c->kode }}</span> <span class="text-ink-muted">{{ $c->deskripsi }}</span></dt>
                                <dd class="tabular shrink-0 font-semibold">{{ Format::angka($r['rerata'] ?? null, 1) }}</dd>
                            </div>
                            <p class="tabular mt-0.5 text-[11.5px] text-ink-faint">
                                {{ __('capaian.rekap.tercapai') }}: {{ $r['tercapai'] ?? 0 }} / {{ $r['jumlah'] ?? 0 }}
                            </p>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        </div>

        <x-card flush :title="__('capaian.rekap.per_mahasiswa')">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.06em] text-ink-muted">
                            <th class="px-4 py-3 font-semibold">{{ __('capaian.rekap.nim') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('capaian.rekap.nama') }}</th>
                            @foreach ($rekap['cpmk'] as $c)
                                <th class="px-2 py-3 text-center font-semibold normal-case" title="{{ $c->rumusan }}">{{ $c->kode }}</th>
                            @endforeach
                            @foreach ($rekap['cpl'] as $c)
                                <th class="border-l border-line px-2 py-3 text-center font-semibold normal-case" title="{{ $c->deskripsi }}">{{ $c->kode }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rekap['mahasiswa'] as $baris)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular px-4 py-2">{{ $baris['mahasiswa']->nim }}</td>
                                <td class="px-4 py-2 font-medium">{{ $baris['mahasiswa']->nama }}</td>
                                @foreach ($rekap['cpmk'] as $c)
                                    @php $n = $baris['cpmk'][$c->id] ?? null; @endphp
                                    <td @class(['tabular px-2 py-2 text-center', 'font-semibold text-warning' => $n !== null && $n < $rekap['ambang']])
                                        @if (($baris['cakupan'][$c->id] ?? 100) < 100) title="{{ __('capaian.istilah.cakupan', ['persen' => $baris['cakupan'][$c->id]]) }}" @endif>
                                        {{ Format::angka($n, 1) }}
                                    </td>
                                @endforeach
                                @foreach ($rekap['cpl'] as $c)
                                    @php $n = $baris['cpl'][$c->id] ?? null; @endphp
                                    <td @class(['tabular border-l border-line px-2 py-2 text-center', 'font-semibold text-warning' => $n !== null && $n < $rekap['ambang']])>
                                        {{ Format::angka($n, 1) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <p class="mt-4 text-[12px] leading-relaxed text-ink-faint">{{ __('capaian.rekap.cara_baca') }}</p>
    @endif
@endsection
