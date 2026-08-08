@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Integrasi Akuntansi')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if (! $aktif)
        {{-- Bukan kegagalan, dan tidak ditulis seperti kegagalan. Integrasi ini
             opsional; banyak kampus memegang buku besarnya di tempat lain. --}}
        <div class="mb-5">
            <x-alert tone="info">
                Integrasi akuntansi <strong>nonaktif</strong>. Tidak ada dokumen yang dicatat
                maupun dikirim, dan penagihan di Open Academic berjalan seperti biasa tanpanya.
                Nyalakan dengan <code class="text-[12px]">AKUNTANSI_DRIVER=palsu</code> untuk
                mencoba, atau <code class="text-[12px]">easyerp</code> beserta API Key untuk
                menyambungkannya sungguhan — lihat <code class="text-[12px]">docs/AKUNTANSI.md</code>.
            </x-alert>
        </div>
    @elseif ($driver === 'palsu')
        {{-- Dikatakan sekeras mungkin. Instalasi yang mengira dirinya sudah
             terhubung padahal memakai driver palsu akan berjalan berbulan-bulan
             dengan buku besar kosong dan tidak ada yang memberi tahu. --}}
        <div class="mb-5">
            <x-alert tone="warning">
                Driver akuntansi masih <strong>palsu</strong>. Dokumen tetap diantre dan dapat
                diekspor, tetapi <strong>tidak ada apa pun yang dikirim ke Easy Accounting</strong>.
                Setel <code class="text-[12px]">AKUNTANSI_DRIVER=easyerp</code> beserta API Key-nya
                untuk menyalakan.
            </x-alert>
        </div>
    @elseif ($tersambung === false)
        <div class="mb-5">
            <x-alert tone="danger">
                Easy Accounting tidak merespons. Dokumen tetap aman di antrean dan akan terkirim
                begitu sambungan pulih — penagihan di sisi Open Academic tidak terpengaruh.
            </x-alert>
        </div>
    @endif

    <div class="mb-5 grid gap-3 sm:grid-cols-4">
        <x-stat-card label="Menunggu" :value="$perStatus['menunggu'] ?? 0" meta="dokumen" />
        <x-stat-card label="Nilai antrean" :value="Format::rupiah($nilaiMenunggu)" meta="belum terbukukan"
            :feature="($perStatus['menunggu'] ?? 0) > 0" />
        <x-stat-card label="Terkirim" :value="$perStatus['terkirim'] ?? 0" meta="dokumen" />
        <x-stat-card label="Gagal" :value="$perStatus['gagal'] ?? 0" meta="perlu tindakan" />
    </div>

    <x-card class="mb-5">
        <div class="flex flex-wrap items-end gap-2">
            @if ($bolehKelola)
                <form method="POST" action="{{ route('admin.akuntansi.kirim') }}">
                    @csrf
                    <x-button type="submit" size="sm">Kirim antrean sekarang</x-button>
                </form>

                @if (($perStatus['gagal'] ?? 0) > 0)
                    <form method="POST" action="{{ route('admin.akuntansi.ulangi-semua') }}">
                        @csrf
                        <x-button type="submit" size="sm" variant="outline">
                            Ulangi semua yang gagal
                        </x-button>
                    </form>
                @endif
            @endif

            <x-button href="{{ route('admin.akuntansi.ekspor') }}" variant="outline" size="sm">
                Ekspor jurnal (CSV)
            </x-button>

            <form method="GET" class="flex items-end gap-2">
                <x-field label="Saring status" name="status" :options="$statusPilihan"
                    :value="request('status')" class="min-w-[180px]" />
                <x-button type="submit" size="sm" variant="ghost">Tampilkan</x-button>
            </form>
        </div>
    </x-card>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <x-card flush title="Antrean Dokumen" meta="100 terbaru, yang gagal di atas">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">Dokumen</th>
                            <th class="px-5 py-3 text-right font-semibold">Nominal</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dokumen as $d)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $d->jenis->label() }}</div>
                                    <div class="text-[11.5px] text-ink-faint">
                                        {{ $d->payload['description'] ?? '—' }}
                                    </div>
                                    <div class="tabular text-[11px] text-ink-faint">
                                        {{ $d->kunci_idempotensi }}
                                        @if ($d->easyerp_nomor) · {{ $d->easyerp_nomor }} @endif
                                    </div>
                                </td>
                                <td class="tabular px-5 py-3 text-right">{{ Format::rupiah($d->nominal) }}</td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$d->status->tone()">{{ $d->status->label() }}</x-chip>

                                    @if ($d->percobaan > 0)
                                        <div class="tabular text-[11px] text-ink-faint">
                                            {{ $d->percobaan }}× percobaan
                                        </div>
                                    @endif

                                    @if ($d->galat)
                                        {{-- Alasan aslinya, bukan "gagal". Penyebab tersering
                                             adalah kode akun yang belum ada di seberang, dan
                                             itu hanya terbaca dari pesan easyERP sendiri. --}}
                                        <p class="mt-1 max-w-md text-[11.5px] leading-relaxed text-danger">
                                            {{ $d->galat }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($bolehKelola && $d->gagal())
                                        <form method="POST" action="{{ route('admin.akuntansi.ulangi', $d) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" variant="outline">Ulangi</x-button>
                                        </form>
                                    @else
                                        <span class="text-[12px] text-ink-faint">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state
                                        title="Antrean kosong"
                                        description="Dokumen muncul di sini saat tagihan diterbitkan, potongan diberikan, atau pembayaran dicatat." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="space-y-5">
            <x-card title="Pemetaan Akun">
                <p class="mb-3 text-[13px] leading-relaxed text-ink-muted">
                    Kode di bawah harus <strong>sudah ada</strong> di Easy Accounting. Penyebab
                    kegagalan tersering adalah kode yang belum dibuat di sana.
                </p>
                <dl class="space-y-1.5 text-[13px]">
                    @foreach ($akun as $nama => $kode)
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ Str::title(str_replace('_', ' ', $nama)) }}</dt>
                            <dd class="tabular font-medium">{{ $kode }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card title="Perlakuan">
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Beasiswa</dt>
                        <dd class="text-right font-medium">
                            {{ $perlakuan['beasiswa'] === 'bruto' ? 'Bruto + beban' : 'Netto' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">PPN</dt>
                        <dd class="text-right font-medium">
                            {{ $perlakuan['kena_ppn'] ? 'Dikenakan' : 'Dikecualikan' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">Mahasiswa terpetakan</dt>
                        <dd class="tabular text-right font-medium">{{ $jumlahPemetaan }}</dd>
                    </div>
                </dl>

                @if ($perlakuan['beasiswa'] === 'bruto')
                    <p class="mt-3 text-[12px] leading-relaxed text-ink-muted">
                        Pendapatan diakui sebesar tarif penuh, potongannya dibukukan sebagai Beban
                        Beasiswa. Laporan laba rugi karenanya menunjukkan berapa yang benar-benar
                        dikeluarkan kampus untuk beasiswa.
                    </p>
                @endif
            </x-card>

            <x-card title="Yang Belum Ada di Sisi Sana">
                {{-- Disebut supaya tidak ditemukan sebagai kejutan saat rekonsiliasi
                     pertama. --}}
                <p class="text-[13px] leading-relaxed text-ink-muted">
                    API Easy Accounting v1 belum memiliki endpoint pembayaran. Penerimaan kas
                    karenanya dikirim sebagai jurnal <em>Dr Kas/Bank, Cr Piutang</em> — buku besar
                    benar, tetapi status invoice di sana tidak ikut berubah menjadi lunas.
                </p>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-muted">
                    Status pelunasan yang sahih untuk saat ini ada di Open Academic.
                </p>
            </x-card>
        </div>
    </div>
@endsection
