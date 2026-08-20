@extends('layouts.app')

@section('title', 'Neo Feeder PDDIKTI')

@php use App\Support\Format; @endphp

@section('aksi')
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.feeder.validasi') }}">
            @csrf
            <x-button variant="outline" type="submit">Jalankan Validasi</x-button>
        </form>

        <form method="POST" action="{{ route('admin.feeder.referensi') }}">
            @csrf
            <x-button variant="outline" type="submit">Tarik Referensi</x-button>
        </form>
    </div>
@endsection

@section('content')
    {{-- Heartbeat: keandalan institusional, bukan animasi ramai. --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap items-center gap-4">
            <span class="animate-heartbeat text-sm {{ $sehat ? 'text-success' : ($aktif ? 'text-danger' : 'text-ink-faint') }}" aria-hidden="true">●</span>

            <div class="min-w-0 flex-1">
                <div class="text-[14.5px] font-semibold">
                    @if (! $aktif)
                        Integrasi Neo Feeder dinonaktifkan
                    @elseif ($sehat)
                        Neo Feeder terhubung
                    @else
                        Neo Feeder tidak merespons
                    @endif
                </div>
                <div class="tabular text-[12px] text-ink-muted">
                    {{ config('feeder.base_url') }} · driver <strong>{{ config('feeder.driver') }}</strong>
                    @if (config('feeder.driver') === 'fake')
                        · payload disimpan di buku besar tanpa dikirim ke mana pun
                    @endif
                </div>
            </div>

            <x-chip :tone="$aktif ? ($sehat ? 'success' : 'danger') : 'neutral'">
                {{ $aktif ? ($sehat ? 'Siap' : 'Gangguan') : 'Nonaktif' }}
            </x-chip>
        </div>
    </x-card>

    {{-- ============ KARTU ENTITAS ============ --}}
    <div class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($entitas as $baris)
            <x-card flush>
                <div class="border-b border-line px-5 py-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="text-[14.5px] font-semibold">{{ $baris['label'] }}</h2>
                            <div class="tabular mt-0.5 text-[11px] text-ink-faint">{{ $baris['action'] }}</div>
                        </div>

                        @if ($baris['gagal'] > 0)
                            <x-chip tone="danger">{{ $baris['gagal'] }} gagal</x-chip>
                        @elseif ($baris['berhasil'] > 0)
                            <x-chip tone="success">Terkirim</x-chip>
                        @else
                            <x-chip tone="neutral">Belum</x-chip>
                        @endif
                    </div>

                    @if ($baris['depends_on'])
                        <div class="mt-2 text-[11px] text-ink-faint">
                            Membutuhkan: {{ implode(', ', $baris['depends_on']) }}
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-4 divide-x divide-line border-b border-line text-center">
                    @foreach ([
                        ['Antre', $baris['antre'], ''],
                        ['Terkirim', $baris['berhasil'], 'text-success'],
                        ['Dilewati', $baris['dilewati'], 'text-ink-muted'],
                        ['Gagal', $baris['gagal'], $baris['gagal'] > 0 ? 'text-danger' : ''],
                    ] as [$label, $nilai, $warna])
                        <div class="px-1 py-3">
                            <div class="tabular font-serif text-[18px] font-semibold leading-none {{ $warna }}">
                                {{ Format::bulat($nilai) }}
                            </div>
                            <div class="mt-1 text-[9.5px] uppercase tracking-[0.06em] text-ink-faint">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-2 px-5 py-3">
                    <form method="POST" action="{{ route('admin.feeder.jalankan', $baris['entity']) }}">
                        @csrf
                        <x-button type="submit" class="px-4 py-2 text-xs" :disabled="! $aktif">Sinkronkan</x-button>
                    </form>

                    @if ($baris['gagal'] > 0)
                        <form method="POST" action="{{ route('admin.feeder.ulangi', $baris['entity']) }}">
                            @csrf
                            <x-button variant="outline" type="submit" class="px-4 py-2 text-xs">
                                Ulangi yang gagal
                            </x-button>
                        </form>
                    @endif

                    @if ($baris['dapat_dibandingkan'])
                        <form method="POST" action="{{ route('admin.feeder.bandingkan', $baris['entity']) }}">
                            @csrf
                            <x-button variant="outline" type="submit" class="px-4 py-2 text-xs" :disabled="! $aktif">
                                Bandingkan
                            </x-button>
                        </form>
                    @else
                        {{--
                            Stated, not hidden. An entity with no compare button
                            and no explanation reads as one that has nothing to
                            compare — which is the opposite of the truth.
                        --}}
                        <span class="text-[11px] text-ink-faint">Belum dapat dibandingkan</span>
                    @endif

                    @if ($baris['terakhir'])
                        <span class="tabular ml-auto text-[11px] text-ink-faint">
                            {{ Format::tanggal($baris['terakhir']) }}
                        </span>
                    @endif
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- ============ VALIDASI PRA-SINKRON ============ --}}
    <x-card class="mb-5" title="Validasi Pra-Sinkron" :meta="$validasiTerakhir->count().' temuan terakhir'" flush>
        @forelse ($validasiTerakhir as $isu)
            <div class="flex flex-wrap items-center gap-3 border-b border-line/50 px-5 py-2.5 last:border-b-0">
                <x-chip :tone="$isu->severity === 'error' ? 'danger' : 'warning'">
                    {{ $isu->severity === 'error' ? 'Ditolak' : 'Perhatian' }}
                </x-chip>

                <span class="tabular w-40 flex-none truncate text-[12.5px] font-medium">{{ $isu->local_label }}</span>
                <span class="min-w-0 flex-1 text-[12.5px] text-ink-muted">{{ $isu->message }}</span>
                <span class="tabular hidden text-[11px] text-ink-faint sm:block">{{ $isu->entity }}</span>
            </div>
        @empty
            <div class="px-5 py-10">
                <x-empty-state
                    title="Belum ada hasil validasi"
                    description="Jalankan validasi untuk melihat baris mana yang akan ditolak PDDIKTI — sebelum satu pun data dikirim."
                />
            </div>
        @endforelse
    </x-card>

    {{-- ============ SELISIH TERHADAP FEEDER ============ --}}
    <x-card
        class="mb-5"
        title="Selisih terhadap Feeder"
        :meta="$selisih->count().' temuan'"
        flush
    >
        @forelse ($selisih as $beda)
            <div class="flex flex-wrap items-start gap-3 border-b border-line/50 px-5 py-2.5 last:border-b-0">
                <x-chip :tone="$beda->jenis->tone()">{{ $beda->jenis->label() }}</x-chip>

                <span class="tabular w-40 flex-none truncate text-[12.5px] font-medium">
                    {{ $beda->label ?? $beda->kunci }}
                </span>

                <div class="min-w-0 flex-1 text-[12.5px] text-ink-muted">
                    @if ($beda->selisih)
                        <div class="tabular space-y-0.5">
                            @foreach ($beda->ringkasSelisih() as $satu)
                                <div>{{ $satu }}</div>
                            @endforeach
                        </div>
                    @else
                        {{ $beda->jenis->saran() }}
                    @endif
                </div>

                <span class="tabular hidden text-[11px] text-ink-faint sm:block">{{ $beda->entity }}</span>
            </div>
        @empty
            <div class="px-5 py-10">
                <x-empty-state
                    title="Belum ada perbandingan"
                    description="Buku besar mencatat apa yang dikirim dari sini. Perbandingan membaca kembali isi Feeder — satu-satunya cara melihat baris yang diubah atau dimasukkan langsung di sana."
                />
            </div>
        @endforelse
    </x-card>

    {{-- ============ BUKU BESAR ============ --}}
    <x-card title="Buku Besar Sinkronisasi" :meta="$term->nama" flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">Waktu</th>
                        <th class="px-5 py-3 font-semibold">Entitas</th>
                        <th class="px-5 py-3 font-semibold">Aksi</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledger as $log)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="tabular px-5 py-2.5 text-ink-muted">
                                {{ Format::tanggal($log->created_at) }} {{ Format::jam($log->created_at) }}
                            </td>
                            <td class="px-5 py-2.5">{{ $log->entity }}</td>
                            <td class="tabular px-5 py-2.5 text-[11.5px] text-ink-faint">{{ $log->action }}</td>
                            <td class="px-5 py-2.5">
                                <x-chip :tone="$log->status->tone()">{{ $log->status->label() }}</x-chip>
                            </td>
                            <td class="px-5 py-2.5 text-ink-muted">
                                @if ($log->error_message)
                                    <span class="text-danger">{{ $log->error_message }}</span>
                                @else
                                    <span class="tabular text-[11.5px] text-ink-faint">
                                        {{ $log->feeder_id ? 'id '.Str::limit($log->feeder_id, 18) : '—' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12">
                                <x-empty-state
                                    title="Buku besar masih kosong"
                                    description="Setiap pengiriman, pelewatan, dan kegagalan tercatat di sini beserta payload-nya — inilah jawaban ketika PDDIKTI dan kampus berbeda pendapat soal apa yang dilaporkan."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
