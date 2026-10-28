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

        @php $dispensasiTerakhir = $aktif->dispensasi->first(); @endphp

        @if (! $aktif->memenuhiSyaratKrs())
            <x-alert tone="warning" class="mb-5">
                Pengisian KRS terkunci hingga pembayaran mencapai
                {{ config('academic.krs.min_payment_percent') }}% dari tagihan pokok
                ({{ Format::rupiah($aktif->pokok()) }}).
                Hubungi Bagian Keuangan bila memerlukan dispensasi.
            </x-alert>
        @elseif ($aktif->dispensasiAktif())
            <x-alert tone="info" class="mb-5" data-dispensasi="berlaku">
                {{ __('keuangan.dispensasi.mahasiswa.aktif', ['tanggal' => Format::tanggal($aktif->dispensasi_sampai)]) }}
                @if ($aktif->dispensasi_catatan)
                    {{ __('keuangan.dispensasi.mahasiswa.aktif_alasan', ['alasan' => $aktif->dispensasi_catatan]) }}
                @endif
            </x-alert>
        @endif

        @if ($dispensasiTerakhir?->status === \App\Enums\StatusDispensasi::Diajukan)
            <x-alert tone="info" class="mb-5" data-dispensasi="menunggu">
                {{ __('keuangan.dispensasi.mahasiswa.menunggu', ['tanggal' => Format::tanggal($dispensasiTerakhir->sampai)]) }}
            </x-alert>
        @elseif ($dispensasiTerakhir?->status === \App\Enums\StatusDispensasi::Ditolak)
            <x-alert tone="warning" class="mb-5" data-dispensasi="ditolak">
                {{ __('keuangan.dispensasi.mahasiswa.ditolak', [
                    'tanggal' => Format::tanggal($dispensasiTerakhir->diputus_at),
                    'catatan' => $dispensasiTerakhir->catatan_keputusan,
                ]) }}
            </x-alert>
        @elseif ($dispensasiTerakhir?->status === \App\Enums\StatusDispensasi::Dicabut && ! $aktif->dispensasiAktif())
            <x-alert tone="warning" class="mb-5" data-dispensasi="dicabut">
                {{ __('keuangan.dispensasi.mahasiswa.dicabut', [
                    'tanggal' => Format::tanggal($dispensasiTerakhir->dicabut_at),
                    'alasan' => $dispensasiTerakhir->alasan_cabut,
                ]) }}
            </x-alert>
        @endif

        @if ($aktif->sisa() > 0 && ! $aktif->dispensasiAktif() && $dispensasiTerakhir?->status !== \App\Enums\StatusDispensasi::Diajukan)
            <x-card :title="__('keuangan.dispensasi.mahasiswa.ajukan_judul')" class="mb-5">
                <p class="mb-4 text-[12.5px] leading-relaxed text-ink-muted">{{ __('keuangan.dispensasi.mahasiswa.ajukan_penjelasan') }}</p>
                <form method="POST" action="{{ route('mahasiswa.tagihan.dispensasi', $aktif) }}"
                    class="grid gap-3 sm:grid-cols-[180px_1fr_auto] sm:items-end">
                    @csrf
                    <x-field :label="__('keuangan.dispensasi.mahasiswa.ajukan_sampai')" name="sampai" type="date" required />
                    <x-field :label="__('keuangan.dispensasi.mahasiswa.ajukan_alasan')" name="alasan" required />
                    <x-button type="submit">{{ __('keuangan.dispensasi.mahasiswa.ajukan_tombol') }}</x-button>
                </form>
            </x-card>
        @endif

        @if ($aktif->dendaDibebaskan())
            <x-alert tone="success" class="mb-5" data-denda="dibebaskan">
                {{ __('keuangan.denda.dibebaskan', [
                    'denda' => Format::rupiah($aktif->pembebasanDenda?->besaran() ?? 0),
                    'tanggal' => Format::tanggal($aktif->denda_dibebaskan_at),
                    'alasan' => $aktif->pembebasanDenda?->alasan,
                ]) }}
            </x-alert>
        @elseif ($aktif->denda > 0)
            <x-alert tone="danger" class="mb-5" data-denda="berjalan">
                {{ __('keuangan.denda.berjalan', ['denda' => Format::rupiah($aktif->denda)]) }}
                @if ($aturanDenda->aktif) {{ $aturanDenda->deskripsi() }} @endif
                {{ __('keuangan.denda.tidak_mengunci_krs') }}
            </x-alert>
        @elseif ($aturanDenda->aktif && $aktif->sisa() > 0)
            <x-alert tone="info" class="mb-5" data-denda="aturan">
                {{ $aturanDenda->deskripsi() }}
                {{ __('keuangan.denda.tidak_mengunci_krs') }}
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
                                <td class="px-5 py-3">
                                    {{ $item->nama }}
                                    @if ($item->jenis === \App\Enums\JenisItemTagihan::Denda)
                                        <x-chip tone="warning" class="ml-1.5">{{ $item->jenis->label() }}</x-chip>
                                    @elseif ($item->alasan)
                                        <div class="text-[11.5px] text-ink-faint">{{ $item->alasan }}</div>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3 text-right {{ $item->nominal < 0 ? 'text-success' : '' }}">
                                    {{ $item->nominal < 0 ? '− ' : '' }}{{ Format::rupiah($item->besaran()) }}
                                </td>
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
