@extends('layouts.app')

@php use App\Support\Format; @endphp

@section('title', __('pmb.admin.antrean_formulir'))

@section('content')
    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <x-card class="mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field :label="__('pmb.admin.cari')" name="cari" type="search" :value="$filter['cari'] ?? ''" class="min-w-[220px] flex-1" />
            <x-button type="submit">{{ __('pmb.admin.terapkan') }}</x-button>
        </form>
    </x-card>

    <x-card flush class="mb-5">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">{{ __('pmb.formulir.nomor') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('pmb.kolom.nama') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('pmb.bayar.sisa') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('pmb.bayar.judul') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menunggu as $p)
                        <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                            <td class="tabular px-5 py-3">{{ $p->nomor_pendaftaran }}</td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $p->nama }}</div>
                                <div class="text-[11.5px] text-ink-faint">{{ $p->gelombang->nama }}</div>
                            </td>
                            <td class="tabular px-5 py-3 text-right">{{ Format::rupiah($p->sisaFormulir()) }}</td>
                            <td class="px-5 py-3">
                                @foreach ($p->berkas as $b)
                                    <a href="{{ route('berkas.pmb', $b) }}" class="text-navy hover:underline">{{ $b->nama_file }}</a>
                                @endforeach
                            </td>
                            <td class="px-5 py-3">
                                @if ($bolehCatat)
                                    <form method="POST" action="{{ route('admin.pmb.pembayaran.store', $p) }}" class="flex flex-wrap items-center justify-end gap-1.5">
                                        @csrf
                                        <input type="number" name="nominal" value="{{ $p->sisaFormulir() }}" min="1" required
                                            class="tabular w-28 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                        <select name="channel" class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                            @foreach ($channel as $c)
                                                <option value="{{ $c }}">{{ __('pmb.channel.'.$c) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="referensi" maxlength="64" placeholder="{{ __('pmb.admin.referensi') }}"
                                            class="w-32 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                        <x-button type="submit" size="sm">{{ __('pmb.admin.catat_bayar') }}</x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10"><x-empty-state :title="__('pmb.admin.antrean_kosong')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($menunggu->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $menunggu->links() }}</div>
        @endif
    </x-card>

    <x-card :title="__('pmb.bayar.riwayat')" flush>
        <ul>
            @foreach ($terakhir as $bayar)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line/60 px-5 py-2.5 text-[12.5px] last:border-b-0">
                    <span class="tabular">
                        {{ Format::tanggal($bayar->paid_at) }} · {{ $bayar->pendaftar->nomor_pendaftaran }} · {{ $bayar->pendaftar->nama }}
                        · {{ Format::rupiah($bayar->nominal) }} · {{ $bayar->pencatat?->nama }}
                    </span>
                    <span class="flex items-center gap-2">
                        <x-chip :tone="$bayar->status->tone()">{{ $bayar->status->label() }}</x-chip>
                        @if ($bolehCatat && $bayar->status === \App\Enums\PaymentStatus::Settlement)
                            <form method="POST" action="{{ route('admin.pmb.pembayaran.batal', $bayar) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="text" name="alasan" required maxlength="255" placeholder="{{ __('pmb.admin.alasan_batal') }}"
                                    class="w-40 rounded-control border border-line-input bg-surface px-2 py-1 text-[12px]">
                                <x-button type="submit" variant="ghost" size="sm">{{ __('pmb.admin.bayar_batal') }}</x-button>
                            </form>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </x-card>
@endsection
