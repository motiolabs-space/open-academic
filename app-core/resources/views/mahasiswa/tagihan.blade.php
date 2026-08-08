@extends('layouts.app')

@section('title', 'Tagihan & Pembayaran')

@php use App\Support\Format; @endphp

@section('content')
    @if ($aktif)
        <div class="mb-5 grid gap-3.5 sm:grid-cols-3">
            <x-stat-card feature label="Sisa Tagihan" :value="Format::rupiah($aktif->sisa())"
                :meta="'Jatuh tempo '.Format::tanggal($aktif->jatuh_tempo)" />

            <x-stat-card label="Total Semester Ini" :value="Format::rupiah($aktif->total)"
                :meta="Format::angka($aktif->persenTerbayar(), 0).'% terbayar'" />

            <x-stat-card label="Status Pembayaran" :meta="$aktif->nomor">
                <x-chip :tone="$aktif->status->tone()" dot>{{ $aktif->status->label() }}</x-chip>
            </x-stat-card>
        </div>

        @if (! $aktif->memenuhiSyaratKrs())
            <x-alert tone="warning" class="mb-5">
                Pengisian KRS terkunci hingga pembayaran mencapai
                {{ config('academic.krs.min_payment_percent') }}% dari total tagihan.
                Hubungi Bagian Keuangan bila memerlukan dispensasi.
            </x-alert>
        @elseif ($aktif->dispensasiAktif())
            <x-alert tone="info" class="mb-5">
                Dispensasi aktif hingga {{ Format::tanggal($aktif->dispensasi_sampai) }} —
                pengisian KRS tetap terbuka meski tagihan belum lunas.
            </x-alert>
        @endif
    @endif

    <div class="grid gap-5 lg:grid-cols-[1.3fr_1fr]">
        <x-card title="Rincian Tagihan Semester Ini" flush>
            @if ($aktif)
                <table class="w-full text-[13px]">
                    <tbody>
                        @foreach ($aktif->item as $item)
                            <tr class="border-b border-line/50 last:border-b-0">
                                <td class="px-5 py-3">{{ $item->nama }}</td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::rupiah($item->nominal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-line bg-highlight/60 font-semibold">
                            <td class="px-5 py-3">Total</td>
                            <td class="tabular px-5 py-3 text-right">{{ Format::rupiah($aktif->total) }}</td>
                        </tr>
                        <tr class="border-t border-line/50">
                            <td class="px-5 py-3 text-ink-muted">Sudah dibayar</td>
                            <td class="tabular px-5 py-3 text-right text-success">
                                − {{ Format::rupiah($aktif->terbayar) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <div class="px-5 py-10">
                    <x-empty-state
                        title="Belum ada tagihan semester ini"
                        description="Tagihan diterbitkan otomatis dari matriks tarif saat semester dibuka."
                    />
                </div>
            @endif
        </x-card>

        <x-card title="Riwayat Pembayaran" flush>
            @php $riwayat = $tagihan->flatMap->pembayaran->sortByDesc('paid_at'); @endphp

            @forelse ($riwayat as $bayar)
                <div class="flex items-center gap-3 border-b border-line/50 px-5 py-3 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <div class="tabular text-[13px] font-semibold">{{ Format::rupiah($bayar->nominal) }}</div>
                        <div class="tabular text-[11.5px] text-ink-faint">
                            {{ Str::upper($bayar->channel ?? $bayar->gateway) }} ·
                            {{ $bayar->nomor_transaksi }} ·
                            {{ Format::tanggal($bayar->paid_at ?? $bayar->created_at) }}
                        </div>
                    </div>

                    <x-chip :tone="$bayar->status->tone()">{{ $bayar->status->label() }}</x-chip>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-[13px] text-ink-faint">Belum ada pembayaran tercatat.</div>
            @endforelse
        </x-card>
    </div>
@endsection
