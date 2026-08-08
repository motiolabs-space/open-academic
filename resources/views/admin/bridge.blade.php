@extends('layouts.app')

@section('title', 'Campus Bridge')

@php use App\Support\Format; @endphp

@section('content')
    {{-- ============ APLIKASI TERHUBUNG ============ --}}
    <div class="mb-5 grid gap-4 md:grid-cols-2">
        @forelse ($konsumen as $baris)
            @php $c = $baris['consumer']; @endphp

            <x-card flush>
                <div class="flex items-start gap-4 border-b border-line px-5 py-4">
                    <span class="grid h-11 w-11 flex-none place-items-center rounded-card bg-navy font-serif text-lg font-semibold text-gold">
                        {{ Str::upper(Str::substr($c->nama, 0, 1)) }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-[15px] font-semibold">{{ $c->nama }}</h2>
                            <x-chip :tone="$c->is_active ? 'success' : 'neutral'" dot>
                                {{ $c->is_active ? 'Terhubung' : 'Nonaktif' }}
                            </x-chip>
                        </div>
                        <p class="mt-1 text-[12.5px] leading-relaxed text-ink-muted">{{ $c->deskripsi }}</p>
                        <div class="tabular mt-1 text-[11.5px] text-ink-faint">
                            {{ $c->base_url ?? '—' }}
                            @if ($c->last_seen_at)
                                · terakhir mengakses {{ Format::tanggal($c->last_seen_at) }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 divide-x divide-line border-b border-line text-center">
                    <div class="px-2 py-3">
                        <div class="tabular font-serif text-[19px] font-semibold leading-none">{{ $baris['token_aktif'] }}</div>
                        <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">Token aktif</div>
                    </div>
                    <div class="px-2 py-3">
                        <div class="tabular font-serif text-[19px] font-semibold leading-none">
                            {{ Format::bulat($baris['panggilan_7_hari']) }}
                        </div>
                        <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">Panggilan 7 hari</div>
                    </div>
                    <div class="px-2 py-3">
                        <div class="tabular font-serif text-[19px] font-semibold leading-none {{ $baris['gagal_kirim'] > 0 ? 'text-danger' : '' }}">
                            {{ $baris['gagal_kirim'] }}
                        </div>
                        <div class="mt-1 text-[10px] uppercase tracking-[0.06em] text-ink-faint">Webhook gagal</div>
                    </div>
                </div>

                {{-- Scope: apa yang boleh dibaca aplikasi ini, dan apa yang tidak. --}}
                <div class="px-5 py-4">
                    <div class="mb-2 text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">
                        Scope token
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($scopeTersedia as $scope => $keterangan)
                            <x-chip
                                :tone="in_array($scope, $c->scopes ?? [], true) ? 'info' : 'neutral'"
                                :title="$keterangan"
                            >
                                {{ in_array($scope, $c->scopes ?? [], true) ? '✓' : '·' }} {{ $scope }}
                            </x-chip>
                        @endforeach
                    </div>

                    @if ($c->webhook_url)
                        <div class="mt-4">
                            <div class="mb-2 text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-faint">
                                Berlangganan event
                            </div>
                            <div class="tabular text-[11.5px] leading-relaxed text-ink-muted">
                                {{ implode(' · ', $c->webhook_events ?? []) }}
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>
        @empty
            <div class="md:col-span-2">
                <x-empty-state
                    title="Belum ada aplikasi terhubung"
                    description="Daftarkan aplikasi konsumen, lalu terbitkan tokennya dengan php artisan openacademic:bridge-token {slug}."
                />
            </div>
        @endforelse
    </div>

    {{-- ============ GRAFIK PENGGUNAAN API ============ --}}
    <x-card class="mb-5" title="Penggunaan API" meta="14 hari terakhir" flush>
        @php $puncak = max(1, $penggunaan->max('jumlah')); @endphp

        <div class="flex items-end gap-1.5 px-5 py-5" style="height: 132px">
            @foreach ($penggunaan as $hari)
                <div class="flex flex-1 flex-col items-center gap-1.5">
                    <div
                        class="w-full rounded-t-sm {{ $hari['jumlah'] > 0 ? 'bg-navy' : 'bg-line' }}"
                        style="height: {{ max(2, round($hari['jumlah'] / $puncak * 88)) }}px"
                        title="{{ Format::tanggal($hari['tanggal']) }} · {{ $hari['jumlah'] }} panggilan"
                    ></div>
                    <span class="tabular text-[9px] text-ink-faint">
                        {{ \Illuminate\Support\Carbon::parse($hari['tanggal'])->format('d') }}
                    </span>
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- ============ LOG WEBHOOK ============ --}}
    <x-card title="Log Pengiriman Webhook" flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Waktu</th>
                        <th class="px-5 py-3 font-semibold">Aplikasi</th>
                        <th class="px-5 py-3 font-semibold">Event</th>
                        <th class="px-5 py-3 text-center font-semibold">Percobaan</th>
                        <th class="px-5 py-3 font-semibold">Tanda tangan</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengiriman as $kirim)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="tabular px-5 py-2.5 text-ink-muted">
                                {{ Format::tanggal($kirim->created_at) }} {{ Format::jam($kirim->created_at) }}
                            </td>
                            <td class="px-5 py-2.5">{{ $kirim->consumer?->nama ?? '—' }}</td>
                            <td class="tabular px-5 py-2.5 text-[11.5px]">{{ $kirim->event }}</td>
                            <td class="tabular px-5 py-2.5 text-center">
                                {{ $kirim->attempts }}/{{ config('bridge.webhooks.max_attempts') }}
                            </td>
                            <td class="px-5 py-2.5">
                                @if ($kirim->signature)
                                    <span class="tabular text-[11px] text-ink-faint" title="{{ $kirim->signature }}">
                                        {{ Str::limit($kirim->signature, 14, '…') }}
                                    </span>
                                @else
                                    <span class="text-[11.5px] text-ink-faint">belum dikirim</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                <x-chip :tone="$kirim->status->tone()">{{ $kirim->status->label() }}</x-chip>
                                @if ($kirim->response_code)
                                    <span class="tabular ml-1 text-[11px] text-ink-faint">HTTP {{ $kirim->response_code }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                @if ($kirim->status->value !== 'delivered')
                                    <form method="POST" action="{{ route('admin.bridge.kirim-ulang', $kirim) }}">
                                        @csrf
                                        <x-button variant="outline" type="submit" class="px-3 py-1.5 text-xs">
                                            Kirim ulang
                                        </x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12">
                                <x-empty-state
                                    title="Belum ada pengiriman webhook"
                                    description="Event terkirim otomatis saat KRS disetujui, nilai difinalisasi, dan peristiwa akademik lain terjadi."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
