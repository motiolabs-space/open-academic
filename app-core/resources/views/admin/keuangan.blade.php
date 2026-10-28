@extends('layouts.app')

@section('title', 'Tagihan & Rekonsiliasi')

@php $rp = fn ($n) => 'Rp'.number_format((float) $n, 0, ',', '.'); @endphp

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Tagihan Terbit" :value="number_format($ringkas['tagihan'])" />
        <x-stat-card label="Nilai Tertagih" :value="$rp($ringkas['tertagih'])" />
        <x-stat-card label="Terkumpul" :value="$rp($ringkas['terkumpul'])" />
        <x-stat-card label="Tunggakan" :value="$rp($ringkas['tunggakan'])"
            :meta="$ringkas['belum_lunas'].' tagihan belum lunas'">
            @if ($ringkas['tunggakan'] > 0)
                <x-chip tone="danger">perlu ditagih</x-chip>
            @else
                <x-chip tone="success">lunas seluruhnya</x-chip>
            @endif
        </x-stat-card>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex min-w-0 flex-col gap-5">
            <x-card>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <x-field label="Cari" name="cari" type="search" :value="$filter['cari'] ?? ''"
                        placeholder="Nomor tagihan, nama, atau NIM…" class="min-w-[220px] flex-1" />
                    <x-field label="Semester" name="term" :value="$filter['term'] ?? ''"
                        :options="$daftarTerm->pluck('nama', 'id')" />
                    <x-field label="Status" name="status" :value="$filter['status'] ?? ''"
                        :options="collect($statusPilihan)->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
                    <x-button type="submit">Terapkan</x-button>
                    @if (array_filter($filter))
                        <x-button variant="ghost" :href="route('admin.keuangan')">Reset</x-button>
                    @endif
                </form>
            </x-card>

            <x-card flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Nomor</th>
                                <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                                <th class="px-5 py-3 text-right font-semibold">Total</th>
                                <th class="px-5 py-3 text-right font-semibold">Terbayar</th>
                                <th class="px-5 py-3 text-center font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Catat Pembayaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tagihan as $t)
                                @php $sisa = (int) $t->total - (int) $t->terbayar; @endphp
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra align-top">
                                    <td class="tabular px-5 py-3">
                                        {{ $t->nomor }}
                                        <div class="text-[11px] text-ink-faint">{{ $t->tahunAkademik->kode }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $t->mahasiswa->nama }}</div>
                                        <div class="tabular text-[11.5px] text-ink-faint">
                                            {{ $t->mahasiswa->nim }} · {{ $t->mahasiswa->prodi->nama }}
                                        </div>
                                    </td>
                                    <td class="tabular px-5 py-3 text-right">
                                        {{ $rp($t->total) }}
                                        @if ($t->denda > 0)
                                            <div class="text-[11px] text-warning">
                                                {{ __('keuangan.denda.termasuk', ['denda' => $rp($t->denda)]) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="tabular px-5 py-3 text-right">
                                        {{ $rp($t->terbayar) }}
                                        @if ($sisa > 0)
                                            <div class="text-[11px] text-danger">sisa {{ $rp($sisa) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <x-chip :tone="$t->status->tone()">{{ $t->status->label() }}</x-chip>
                                        @if ($t->dendaDibebaskan())
                                            <div class="mt-1.5"
                                                title="{{ $t->pembebasanDenda?->alasan }} — {{ $t->pembebasanDenda?->pemutus?->nama }}">
                                                <x-chip tone="neutral">{{ __('keuangan.denda.chip_dibebaskan') }}</x-chip>
                                            </div>
                                        @endif
                                        @if ($t->dispensasiAktif())
                                            <div class="mt-1.5" title="{{ $t->dispensasi_catatan }}">
                                                <x-chip tone="info">{{ __('keuangan.dispensasi.chip_berlaku', ['tanggal' => \App\Support\Format::tanggal($t->dispensasi_sampai)]) }}</x-chip>
                                            </div>
                                        @elseif ($sisa > 0)
                                            <a href="{{ route('admin.keuangan.dispensasi', ['tagihan' => $t->nomor]) }}"
                                                class="mt-1.5 inline-block text-[11.5px] font-semibold text-navy hover:underline">
                                                {{ __('keuangan.dispensasi.tautan_baris') }}
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($t->denda > 0)
                                            <form method="POST" action="{{ route('admin.keuangan.denda.bebaskan', $t) }}"
                                                class="mb-1.5 flex flex-wrap items-center justify-end gap-1.5"
                                                data-confirm="Bebaskan denda {{ $rp($t->denda) }}? Denda pada tagihan ini tidak akan dihitung lagi.">
                                                @csrf
                                                <input type="text" name="alasan" required minlength="5" maxlength="500"
                                                    placeholder="Alasan pembebasan"
                                                    class="w-40 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <x-button type="submit" variant="outline" size="sm">
                                                    {{ __('keuangan.denda.tombol_bebaskan') }}
                                                </x-button>
                                            </form>
                                        @endif
                                        @if ($sisa > 0)
                                            <form method="POST" action="{{ route('admin.keuangan.pembayaran', $t) }}"
                                                class="flex flex-wrap items-center justify-end gap-1.5">
                                                @csrf
                                                <input type="number" name="nominal" required min="1" max="{{ $sisa }}"
                                                    value="{{ $sisa }}"
                                                    class="tabular w-28 rounded-control border border-line-input bg-surface px-2 py-1.5 text-right text-[12px]">
                                                <select name="channel"
                                                    class="rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                    <option value="tunai">Tunai</option>
                                                    <option value="transfer">Transfer</option>
                                                    <option value="va">VA</option>
                                                    <option value="qris">QRIS</option>
                                                </select>
                                                <x-button type="submit" size="sm">Catat</x-button>
                                            </form>
                                        @else
                                            <span class="text-[11.5px] text-ink-faint">lunas</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12">
                                        <x-empty-state title="Tidak ada tagihan"
                                            description="Terbitkan tagihan semester lewat panel di sebelah." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($tagihan->hasPages())
                    <div class="border-t border-line px-5 py-3">{{ $tagihan->links() }}</div>
                @endif
            </x-card>

            <x-card title="Pembayaran Terbaru" flush>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-[13px]">
                        <thead>
                            <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                <th class="px-5 py-3 font-semibold">Referensi</th>
                                <th class="px-5 py-3 font-semibold">Mahasiswa</th>
                                <th class="px-5 py-3 text-right font-semibold">Nominal</th>
                                <th class="px-5 py-3 text-center font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pembayaranTerbaru as $p)
                                <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                    <td class="tabular px-5 py-3">
                                        {{ $p->nomor_transaksi }}
                                        <div class="text-[11px] text-ink-faint">{{ $p->gateway }} · {{ $p->channel }}</div>
                                    </td>
                                    <td class="px-5 py-3">{{ $p->mahasiswa->nama }}</td>
                                    <td class="tabular px-5 py-3 text-right">{{ $rp($p->nominal) }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <x-chip :tone="$p->status->tone()">{{ $p->status->label() }}</x-chip>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($p->status->value === 'settlement')
                                            <form method="POST" action="{{ route('admin.keuangan.pembayaran.batal', $p) }}"
                                                class="flex items-center justify-end gap-1.5">
                                                @csrf
                                                <input type="text" name="alasan" required placeholder="Alasan"
                                                    class="w-32 rounded-control border border-line-input bg-surface px-2 py-1.5 text-[12px]">
                                                <x-button type="submit" variant="danger" size="sm">Batalkan</x-button>
                                            </form>
                                        @else
                                            <span class="text-[11.5px] text-ink-faint">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-[13px] text-ink-faint">
                                        Belum ada pembayaran tercatat.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <x-card title="Terbitkan Tagihan Semester">
            @if ($pratinjau)
                <div class="mb-4 rounded-card border-2 border-navy bg-highlight px-4 py-3.5">
                    <div class="mb-2 text-[12px] font-bold uppercase tracking-[0.08em] text-navy">Pratinjau</div>
                    <ul class="flex flex-col gap-1.5 text-[13px]">
                        <li><strong>{{ $pratinjau['akan_terbit'] }}</strong> tagihan akan diterbitkan</li>
                        <li class="tabular">Senilai <strong>{{ $rp($pratinjau['total_rupiah']) }}</strong></li>
                        <li class="text-ink-muted">{{ $pratinjau['sudah_ada'] }} sudah ditagih, akan dilewati</li>
                        @if ($pratinjau['tanpa_tarif'] > 0)
                            <li class="font-semibold text-danger">
                                {{ $pratinjau['tanpa_tarif'] }} mahasiswa tanpa tarif yang cocok — mereka
                                tidak akan ditagih sama sekali.
                            </li>
                        @endif
                    </ul>

                    <form method="POST" action="{{ route('admin.keuangan.terbitkan') }}" class="mt-3.5"
                        data-confirm="Terbitkan {{ $pratinjau['akan_terbit'] }} tagihan? Tindakan ini tidak dapat dibatalkan massal.">
                        @csrf
                        <input type="hidden" name="tahun_akademik_id" value="{{ $pratinjau['tahun_akademik_id'] }}">
                        <input type="hidden" name="angkatan" value="{{ $pratinjau['angkatan'] ?? '' }}">
                        <input type="hidden" name="prodi_id" value="{{ $pratinjau['prodi_id'] ?? '' }}">
                        <x-button type="submit" variant="gold" class="w-full">Terbitkan Sekarang</x-button>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.keuangan.pratinjau') }}" class="flex flex-col gap-3.5">
                @csrf
                <x-field label="Semester" name="tahun_akademik_id" required
                    :value="$termAktif?->id" :options="$daftarTerm->pluck('nama', 'id')" />
                <x-field label="Angkatan" name="angkatan" type="number"
                    hint="Kosongkan untuk seluruh angkatan." />
                <x-field label="Program Studi" name="prodi_id"
                    :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])"
                    hint="Kosongkan untuk seluruh prodi." />

                <p class="rounded-card border border-line bg-canvas px-3.5 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
                    Hanya mahasiswa berstatus aktif yang ditagih. Menjalankan ulang aman —
                    yang sudah punya tagihan pada semester ini dilewati, bukan ditagih dua kali.
                </p>

                <x-button type="submit" variant="outline" class="w-full">Lihat Pratinjau</x-button>
            </form>

            <div class="mt-5 border-t border-line pt-4">
                <div class="mb-1.5 text-[11px] font-bold uppercase tracking-[0.08em] text-ink-muted">Denda Keterlambatan</div>
                @if ($aturanDenda->aktif)
                    <p class="text-[12px] leading-relaxed text-ink-muted">
                        {{ $aturanDenda->deskripsi() }}
                        Dihitung tiap dini hari oleh penjadwal; pembebasan dicatat per tagihan
                        dengan alasan dan nama staf.
                    </p>
                @else
                    <p class="text-[12px] leading-relaxed text-ink-muted">
                        Nonaktif. Nyalakan lewat <code class="tabular">PAYMENT_DENDA_AKTIF=true</code>
                        dan aturannya di <code>.env</code>; denda yang sudah tercatat tetap tampil.
                    </p>
                @endif
            </div>
        </x-card>
    </div>
@endsection
