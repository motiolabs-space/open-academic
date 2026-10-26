{{--
    Rekap angka kredit satu dosen: AK terakhir + Kum A–E yang diakui = AK
    kumulatif, dan jaraknya ke syarat jenjang berikutnya.

    $rekap  App\DTOs\Sdm\RekapAngkaKredit
--}}
@php
    use App\Enums\UnsurAngkaKredit;
    use App\Support\Format;

    $ak = fn (?int $ratus): string => $ratus === null ? '—' : Format::angka($ratus / 100, 2);
@endphp

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
    <x-card flush :title="__('angka_kredit.per_dosen')"
        :meta="($rekap->jabatan?->label() ?? __('angka_kredit.tanpa_jabatan'))
            .($rekap->tmtJabatan ? ' · TMT '.Format::tanggal($rekap->tmtJabatan) : '')">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">{{ __('angka_kredit.kolom.unsur') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('angka_kredit.kolom.nilai') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-line/50">
                        <td class="px-5 py-2.5 text-ink-muted">{{ __('angka_kredit.kolom.ak_terakhir') }}</td>
                        <td class="tabular px-5 py-2.5 text-right">{{ $ak($rekap->akTerakhirRatus) }}</td>
                    </tr>
                    @foreach (UnsurAngkaKredit::cases() as $unsur)
                        <tr class="border-b border-line/50 odd:bg-zebra">
                            <td class="px-5 py-2.5">Kum {{ $unsur->kode() }} — {{ $unsur->label() }}</td>
                            <td class="tabular px-5 py-2.5 text-right">
                                {{ $ak($rekap->diakui($unsur)) }}
                                @if ($rekap->dipangkas($unsur))
                                    <span class="text-[11.5px] text-warning">({{ $ak($rekap->kum($unsur)) }})</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr class="border-b border-line/50 font-semibold">
                        <td class="px-5 py-2.5">{{ __('angka_kredit.kolom.dupak') }}</td>
                        <td class="tabular px-5 py-2.5 text-right">{{ $ak($rekap->totalDupakRatus) }}</td>
                    </tr>
                    <tr class="font-semibold">
                        <td class="px-5 py-2.5">{{ __('angka_kredit.kolom.ak_kumulatif') }}</td>
                        <td class="tabular px-5 py-2.5 text-right">{{ $ak($rekap->akKumulatifRatus) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="space-y-1 border-t border-line px-5 py-3 text-[11.5px] text-ink-faint">
            <p>{{ __('angka_kredit.petunjuk.sesudah_tmt') }}</p>
            <p>{{ __('angka_kredit.petunjuk.batas', ['persen' => config('angka_kredit.batas_persen.pengabdian')]) }}</p>
            @if ($rekap->adaPerkiraan)
                <p class="text-warning">{{ __('angka_kredit.petunjuk.perkiraan') }}</p>
            @endif
        </div>
    </x-card>

    <x-card :title="__('angka_kredit.kolom.kesiapan')">
        @if ($rekap->berikutnya === null)
            <p class="text-[13px] text-ink-muted">{{ __('angka_kredit.kesiapan.puncak') }}</p>
        @else
            <dl class="space-y-2 text-[13px]">
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">{{ __('angka_kredit.kolom.berikutnya') }}</dt>
                    <dd class="font-medium">{{ $rekap->berikutnya->label() }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">{{ __('angka_kredit.kolom.syarat') }}</dt>
                    <dd class="tabular">{{ $ak($rekap->syaratRatus) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">{{ __('angka_kredit.kolom.selisih') }}</dt>
                    <dd class="tabular">{{ $ak($rekap->selisihRatus) }}</dd>
                </div>
            </dl>

            <div class="mt-4">
                @if ($rekap->siap)
                    <x-chip tone="success">{{ __('angka_kredit.kesiapan.siap') }}</x-chip>
                @else
                    <div class="flex flex-wrap gap-2">
                        @if ($rekap->selisihRatus > 0)
                            <x-chip tone="warning">{{ __('angka_kredit.kesiapan.belum', ['selisih' => $ak($rekap->selisihRatus)]) }}</x-chip>
                        @endif
                        @unless ($rekap->memenuhiPenelitian)
                            <x-chip tone="warning">{{ __('angka_kredit.kesiapan.penelitian', ['minimum' => $ak($rekap->penelitianMinimumRatus)]) }}</x-chip>
                        @endunless
                    </div>
                @endif
            </div>
        @endif
    </x-card>
</div>
