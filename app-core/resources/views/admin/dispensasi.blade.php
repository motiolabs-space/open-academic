@extends('layouts.app')

@section('title', __('keuangan.dispensasi.judul'))

@php use App\Support\Format; @endphp

@section('content')
    <x-alert tone="info" class="mb-5">{{ __('keuangan.dispensasi.penjelasan') }}</x-alert>

    <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-5">
            <x-card :title="__('keuangan.dispensasi.menunggu.judul')" :meta="$menunggu->count()" flush>
                @forelse ($menunggu as $d)
                    <div class="border-b border-line/60 px-5 py-4 last:border-b-0" data-dispensasi="{{ $d->uuid }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div>
                                <div class="font-medium">{{ $d->tagihan->mahasiswa->nama }}</div>
                                <div class="tabular text-[11.5px] text-ink-faint">
                                    {{ $d->tagihan->mahasiswa->nim }} · {{ $d->tagihan->nomor }} ·
                                    {{ __('keuangan.dispensasi.kolom.sisa') }} {{ Format::rupiah($d->tagihan->sisa()) }}
                                </div>
                            </div>
                            <div class="tabular text-[12.5px]">
                                {{ __('keuangan.dispensasi.kolom.sampai') }} <strong>{{ Format::tanggal($d->sampai) }}</strong>
                            </div>
                        </div>
                        <p class="mt-2 text-[12.5px] leading-relaxed text-ink-muted">{{ $d->alasan }}</p>

                        @if ($bolehKelola)
                            <div class="mt-3 flex flex-wrap items-end gap-2">
                                <form method="POST" action="{{ route('admin.keuangan.dispensasi.putuskan', $d) }}"
                                    class="flex flex-wrap items-end gap-2"
                                    data-confirm="{{ __('keuangan.dispensasi.menunggu.konfirmasi_setujui') }}">
                                    @csrf
                                    <input type="hidden" name="setujui" value="1">
                                    <input type="date" name="sampai" value="{{ $d->sampai->toDateString() }}"
                                        aria-label="{{ __('keuangan.dispensasi.kolom.sampai') }}"
                                        class="tabular rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                    <x-button type="submit" size="sm">{{ __('keuangan.dispensasi.menunggu.setujui') }}</x-button>
                                </form>
                                <form method="POST" action="{{ route('admin.keuangan.dispensasi.putuskan', $d) }}"
                                    class="flex flex-wrap items-end gap-2"
                                    data-confirm="{{ __('keuangan.dispensasi.menunggu.konfirmasi_tolak') }}">
                                    @csrf
                                    <input type="hidden" name="setujui" value="0">
                                    <input type="text" name="catatan" required maxlength="500"
                                        placeholder="{{ __('keuangan.dispensasi.menunggu.catatan') }}"
                                        class="w-48 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                    <x-button type="submit" variant="danger" size="sm">{{ __('keuangan.dispensasi.menunggu.tolak') }}</x-button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-faint">{{ __('keuangan.dispensasi.menunggu.kosong') }}</div>
                @endforelse
            </x-card>

            <x-card :title="__('keuangan.dispensasi.berlaku.judul')" :meta="$berlaku->count()" flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">{{ __('keuangan.dispensasi.kolom.mahasiswa') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('keuangan.dispensasi.kolom.tagihan') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('keuangan.dispensasi.kolom.sampai') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('keuangan.dispensasi.kolom.alasan') }}</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($berlaku as $d)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top" data-dispensasi="{{ $d->uuid }}">
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $d->tagihan->mahasiswa->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">{{ $d->tagihan->mahasiswa->nim }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3">
                                        {{ $d->tagihan->nomor }}
                                        <div class="text-[11px] text-ink-faint">{{ __('keuangan.dispensasi.kolom.sisa') }} {{ Format::rupiah($d->tagihan->sisa()) }}</div>
                                    </td>
                                    <td class="tabular px-5 py-3">{{ Format::tanggal($d->sampai) }}</td>
                                    <td class="px-5 py-3 text-[12.5px] text-ink-muted">
                                        {{ $d->alasan }}
                                        <div class="mt-1 text-[11px] text-ink-faint">
                                            {{ __('keuangan.dispensasi.kolom.pemberi') }} {{ $d->pemutus?->nama ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($bolehKelola)
                                            <form method="POST" action="{{ route('admin.keuangan.dispensasi.cabut', $d) }}"
                                                class="flex flex-wrap items-center justify-end gap-1.5"
                                                data-confirm="{{ __('keuangan.dispensasi.berlaku.konfirmasi_cabut') }}">
                                                @csrf
                                                <input type="text" name="alasan" required minlength="5" maxlength="500"
                                                    placeholder="{{ __('keuangan.dispensasi.berlaku.alasan_cabut') }}"
                                                    class="w-40 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <x-button type="submit" variant="outline" size="sm">{{ __('keuangan.dispensasi.berlaku.cabut') }}</x-button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-[13px] text-ink-faint">{{ __('keuangan.dispensasi.berlaku.kosong') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card :title="__('keuangan.dispensasi.riwayat.judul')" flush>
                @forelse ($riwayat as $d)
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line/60 px-5 py-3 text-[12.5px] last:border-b-0">
                        <div>
                            <span class="font-medium">{{ $d->tagihan->mahasiswa->nama }}</span>
                            <span class="tabular text-ink-faint">· {{ $d->tagihan->nomor }} · {{ Format::tanggal($d->sampai) }}</span>
                            <div class="text-ink-muted">{{ $d->alasan_cabut ?? $d->catatan_keputusan ?? $d->alasan }}</div>
                        </div>
                        @if ($d->status === \App\Enums\StatusDispensasi::Disetujui)
                            <x-chip tone="neutral">{{ __('keuangan.dispensasi.kedaluwarsa') }}</x-chip>
                        @else
                            <x-chip :tone="$d->status->tone()">{{ $d->status->label() }}</x-chip>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-faint">{{ __('keuangan.dispensasi.riwayat.kosong') }}</div>
                @endforelse
            </x-card>
        </div>

        @if ($bolehKelola)
            <x-card :title="__('keuangan.dispensasi.beri.judul')">
                <form method="POST" action="{{ route('admin.keuangan.dispensasi.beri') }}" class="space-y-4"
                    data-confirm="{{ __('keuangan.dispensasi.beri.konfirmasi') }}">
                    @csrf
                    <x-field :label="__('keuangan.dispensasi.beri.tagihan')" name="tagihan" :value="$nomorAwal" required
                        :hint="__('keuangan.dispensasi.beri.tagihan_bantuan')" />
                    <x-field :label="__('keuangan.dispensasi.beri.sampai')" name="sampai" type="date" required />
                    <x-field :label="__('keuangan.dispensasi.beri.alasan')" name="alasan" type="textarea" required />
                    <x-button type="submit" class="w-full">{{ __('keuangan.dispensasi.beri.tombol') }}</x-button>
                </form>
            </x-card>
        @endif
    </div>
@endsection
