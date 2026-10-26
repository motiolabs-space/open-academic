@php
    use App\Enums\UnsurAngkaKredit;
    use App\Support\Format;

    $ak = fn (?int $ratus): string => $ratus === null ? '—' : Format::angka($ratus / 100, 2);
@endphp
@extends('layouts.app')

@section('title', __('angka_kredit.judul'))

@section('content')
    @include('kepegawaian.pesan')

    <div class="mb-5">
        <x-alert tone="info">{{ __('angka_kredit.pengantar') }}</x-alert>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-2">
        @can('ekspor', App\Models\Sdm\AngkaKreditDosen::class)
            <x-button size="sm" :href="route('admin.angka-kredit.ekspor')">
                {{ __('angka_kredit.aksi.ekspor') }} · {{ $jumlahLembar }}
            </x-button>
        @endcan
        @can('susun', App\Models\Sdm\AngkaKreditDosen::class)
            <form method="POST" action="{{ route('admin.angka-kredit.susun-semua') }}">
                @csrf
                <x-button type="submit" size="sm" variant="outline">{{ __('angka_kredit.aksi.susun_semua') }}</x-button>
            </form>
        @endcan
    </div>

    <p class="mb-3 text-[12.5px] text-ink-muted">
        {{ __('angka_kredit.petunjuk.penyetaraan', [
            'tanggal' => Format::tanggal(config('angka_kredit.penyetaraan.tmt_paling_lambat')),
        ]) }}
    </p>

    @if ($tertahan !== [])
        <div class="mb-5">
            <x-alert tone="warning">
                {{ __('angka_kredit.pesan.tertahan', ['jumlah' => count($tertahan)]) }}
                {{ collect($tertahan)->map(fn ($t) => $t['dosen']->nama.' ('.$t['alasan'].')')->implode(', ') }}
            </x-alert>
        </div>
    @endif

    <div class="space-y-5">
        <x-card flush :title="__('angka_kredit.per_jenjang')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.jabatan') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.jumlah') }}</th>
                            @foreach (UnsurAngkaKredit::cases() as $unsur)
                                <th class="px-5 py-3 text-right font-semibold">Kum {{ $unsur->kode() }}</th>
                            @endforeach
                            <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.dupak') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.siap') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($perJenjang as $j)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-2.5">{{ $j['jabatan']?->label() ?? __('angka_kredit.tanpa_jabatan') }}</td>
                                <td class="tabular px-5 py-2.5 text-right">{{ $j['jumlah'] }}</td>
                                @foreach (UnsurAngkaKredit::cases() as $unsur)
                                    <td class="tabular px-5 py-2.5 text-right">{{ $ak($j['diakui_ratus'][$unsur->value]) }}</td>
                                @endforeach
                                <td class="tabular px-5 py-2.5 text-right">{{ $ak($j['dupak_ratus']) }}</td>
                                <td class="tabular px-5 py-2.5 text-right">{{ $j['siap'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card flush :title="__('angka_kredit.per_dosen')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.dosen') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.jabatan') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.ak_terakhir') }}</th>
                            @foreach (UnsurAngkaKredit::cases() as $unsur)
                                <th class="px-5 py-3 text-right font-semibold">{{ $unsur->kode() }}</th>
                            @endforeach
                            <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.ak_kumulatif') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.kesiapan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekap as $r)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-2.5">
                                    <a class="font-medium text-navy hover:underline" href="{{ route('admin.angka-kredit.show', $r->dosen) }}">
                                        {{ $r->dosen->namaLengkap() }}
                                    </a>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $r->dosen->nuptk ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-2.5">
                                    {{ $r->jabatan?->label() ?? __('angka_kredit.tanpa_jabatan') }}
                                    @if ($r->tmtJabatan)
                                        <div class="tabular text-[11.5px] text-ink-faint">TMT {{ Format::tanggal($r->tmtJabatan) }}</div>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-2.5 text-right">{{ $ak($r->akTerakhirRatus) }}</td>
                                @foreach (UnsurAngkaKredit::cases() as $unsur)
                                    <td @class(['tabular px-5 py-2.5 text-right', 'text-warning' => $r->dipangkas($unsur)])>
                                        {{ $ak($r->diakui($unsur)) }}
                                    </td>
                                @endforeach
                                <td class="tabular px-5 py-2.5 text-right font-medium">{{ $ak($r->akKumulatifRatus) }}</td>
                                <td class="px-5 py-2.5">
                                    @if ($r->berikutnya === null)
                                        <x-chip>{{ __('angka_kredit.kesiapan.puncak') }}</x-chip>
                                    @elseif ($r->siap)
                                        <x-chip tone="success">{{ __('angka_kredit.kesiapan.siap') }}</x-chip>
                                    @elseif ($r->selisihRatus > 0)
                                        <x-chip tone="warning">{{ __('angka_kredit.kesiapan.belum', ['selisih' => $ak($r->selisihRatus)]) }}</x-chip>
                                    @else
                                        <x-chip tone="warning">{{ __('angka_kredit.kesiapan.penelitian', ['minimum' => $ak($r->penelitianMinimumRatus)]) }}</x-chip>
                                    @endif
                                    @if ($r->adaPerkiraan)
                                        <x-chip tone="neutral">{{ __('angka_kredit.perkiraan') }}</x-chip>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-6 text-center text-ink-muted">{{ __('angka_kredit.kosong.rekap') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
