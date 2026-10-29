@extends('layouts.app')

@section('title', 'Yudisium & Wisuda')

@php use App\Support\Format; @endphp

@section('content')
    {{-- ============ MENUNGGU PENETAPAN ============ --}}
    <h2 class="mb-3 text-[11px] font-semibold uppercase tracking-[0.1em] text-ink-faint">Menunggu Penetapan</h2>

    <div class="mb-8 grid gap-4 md:grid-cols-2">
        @forelse ($diajukan as $baris)
            @php $y = $baris['yudisium']; $s = $baris['syarat']; @endphp

            <x-card flush x-data="{ batal: false }">
                <div class="flex items-start gap-3 border-b border-line px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <div class="text-[15px] font-semibold">{{ $y->mahasiswa->nama }}</div>
                        <div class="tabular mt-0.5 text-xs text-ink-muted">
                            {{ $y->mahasiswa->nim }} · {{ $y->mahasiswa->prodi->namaLengkap() }}
                        </div>
                        @if ($y->judul_tugas_akhir)
                            <div class="mt-1 text-[12px] italic text-ink-muted">“{{ $y->judul_tugas_akhir }}”</div>
                        @endif
                    </div>

                    <div class="tabular text-right">
                        <div class="font-serif text-[22px] font-semibold leading-none">{{ Format::angka($s->ipk) }}</div>
                        <div class="text-[11px] text-ink-faint">{{ $s->sksLulus }} SKS</div>
                    </div>
                </div>

                {{-- Checklist syarat: menjelaskan apa yang diverifikasi, bukan
                     sekadar boleh atau tidak. --}}
                <div class="divide-y divide-line/50">
                    @foreach ($s->rincian as $syarat)
                        <div class="flex items-center gap-3 px-5 py-2.5">
                            <span @class([
                                'grid h-5 w-5 flex-none place-items-center rounded-full text-[11px] font-bold',
                                'bg-success-bg text-success' => $syarat['terpenuhi'],
                                'bg-danger-bg text-danger' => ! $syarat['terpenuhi'],
                            ])>{{ $syarat['terpenuhi'] ? '✓' : '×' }}</span>

                            <span class="flex-1 text-[13px]">{{ $syarat['label'] }}</span>
                            <span class="tabular text-[11.5px] {{ $syarat['terpenuhi'] ? 'text-ink-faint' : 'text-danger' }}">
                                {{ $syarat['keterangan'] }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-line px-5 py-4">
                    @if ($s->memenuhi())
                        <form method="POST" action="{{ route('admin.yudisium.tetapkan', $y) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="flex-1">
                                <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                                    Nomor SK
                                </span>
                                <input
                                    type="text"
                                    name="nomor_sk"
                                    value="{{ $y->nomor_sk }}"
                                    placeholder="SK/2026/001"
                                    class="w-full rounded-control border border-line-input bg-surface px-3 py-2 text-[13px] outline-none focus:border-navy"
                                >
                            </label>
                            <x-button variant="gold" type="submit">Tetapkan Lulus</x-button>
                        </form>
                    @else
                        <x-alert tone="warning">
                            Belum dapat ditetapkan: {{ implode(', ', $s->belumTerpenuhi()) }}.
                        </x-alert>
                    @endif
                </div>
            </x-card>
        @empty
            <div class="md:col-span-2">
                <x-empty-state
                    title="Tidak ada pengajuan yudisium"
                    description="Mahasiswa yang sudah memenuhi seluruh syarat muncul sebagai kandidat di bawah."
                />
            </div>
        @endforelse
    </div>

    {{-- ============ KANDIDAT ============ --}}
    <x-card class="mb-8" title="Kandidat Memenuhi Syarat" :meta="$kandidat->count().' mahasiswa'" flush>
        @forelse ($kandidat as $baris)
            <form
                method="POST"
                action="{{ route('admin.yudisium.ajukan') }}"
                class="flex flex-wrap items-center gap-3 border-b border-line/50 px-5 py-3 last:border-b-0"
            >
                @csrf
                <input type="hidden" name="mahasiswa_uuid" value="{{ $baris['mahasiswa']->uuid }}">

                <div class="min-w-0 flex-1">
                    <div class="text-[13.5px] font-semibold">{{ $baris['mahasiswa']->nama }}</div>
                    <div class="tabular text-[11.5px] text-ink-faint">
                        {{ $baris['mahasiswa']->nim }} · {{ $baris['syarat']->sksLulus }} SKS ·
                        IPK {{ Format::angka($baris['syarat']->ipk) }}
                    </div>
                </div>

                <input
                    type="text"
                    name="judul_tugas_akhir"
                    placeholder="Judul tugas akhir (opsional)"
                    class="w-full max-w-xs rounded-control border border-line-input bg-surface px-3 py-1.5 text-[12.5px] outline-none focus:border-navy"
                >

                <x-button variant="outline" type="submit" class="px-4 py-2 text-xs">Ajukan Yudisium</x-button>
            </form>
        @empty
            <div class="px-5 py-8 text-center text-[13px] text-ink-faint">
                Belum ada mahasiswa yang memenuhi seluruh syarat kelulusan.
            </div>
        @endforelse
    </x-card>

    {{-- ============ MENUNGGU UJI KOMPETENSI ============ --}}
    @if ($menungguUkom->isNotEmpty())
        <x-card :title="__('ukom.judul')" :meta="$menungguUkom->count().' mahasiswa'" flush class="mb-8">
            <p class="border-b border-line px-5 py-3 text-[12px] leading-relaxed text-ink-muted">{{ __('ukom.keterangan') }}</p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('ukom.kolom.mahasiswa') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('ukom.kolom.yudisium') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('ukom.kolom.riwayat') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('ukom.kolom.catat') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($menungguUkom as $m)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-ukom="{{ $m->nim }}">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $m->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $m->nim }} · {{ $m->prodi->namaLengkap() }}</div>
                                </td>
                                <td class="tabular px-5 py-3 text-ink-muted">
                                    {{ Format::tanggal($m->yudisium?->tanggal_yudisium) }}
                                    @if ($m->yudisium?->nomor_sk)
                                        <div class="text-[11px] text-ink-faint">{{ $m->yudisium->nomor_sk }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @forelse ($m->ujiKompetensi as $u)
                                        <div class="tabular text-[12px]">
                                            {{ Format::tanggal($u->tanggal_ujian) }}
                                            <x-chip :tone="$u->hasil->tone()" class="ml-1">{{ $u->hasil->label() }}</x-chip>
                                        </div>
                                    @empty
                                        <span class="text-ink-faint">—</span>
                                    @endforelse
                                </td>
                                <td class="px-5 py-3">
                                    <form method="POST" action="{{ route('admin.yudisium.ukom', $m) }}"
                                        class="flex flex-wrap items-end gap-2"
                                        data-confirm="{{ __('ukom.form.konfirmasi_lulus', ['nama' => $m->nama]) }}" data-confirm-if="hasil=lulus">
                                        @csrf
                                        <label class="block">
                                            <span class="sr-only">{{ __('ukom.form.tanggal') }}</span>
                                            <input type="date" name="tanggal_ujian" required max="{{ now()->toDateString() }}"
                                                aria-label="{{ __('ukom.form.tanggal') }}"
                                                class="tabular rounded-control border border-line-input bg-surface px-2.5 py-1.5 text-[12.5px] outline-none focus:border-navy">
                                        </label>
                                        <select name="hasil" required aria-label="{{ __('ukom.form.hasil') }}"
                                            class="rounded-control border border-line-input bg-surface px-2.5 py-1.5 text-[12.5px] outline-none focus:border-navy">
                                            @foreach (\App\Enums\HasilUjiKompetensi::cases() as $h)
                                                <option value="{{ $h->value }}">{{ $h->label() }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="nomor_sertifikat" maxlength="64"
                                            placeholder="{{ __('ukom.form.nomor_sertifikat') }}"
                                            aria-label="{{ __('ukom.form.nomor_sertifikat') }}"
                                            class="w-44 rounded-control border border-line-input bg-surface px-2.5 py-1.5 text-[12.5px] outline-none focus:border-navy">
                                        <x-button type="submit" size="sm">{{ __('ukom.form.simpan') }}</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- ============ SUDAH DITETAPKAN ============ --}}
    <x-card title="Lulusan Ditetapkan" flush>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        <th class="px-5 py-3 font-semibold">NIM</th>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-5 py-3 font-semibold">Program Studi</th>
                        <th class="px-5 py-3 text-center font-semibold">SKS</th>
                        <th class="px-5 py-3 text-center font-semibold">IPK</th>
                        <th class="px-5 py-3 font-semibold">Predikat</th>
                        <th class="px-5 py-3 font-semibold">Tanggal Lulus</th>
                        <th class="px-5 py-3 font-semibold">Siap PIN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ditetapkan as $y)
                        <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                            <td class="tabular px-5 py-2.5">{{ $y->mahasiswa->nim }}</td>
                            <td class="px-5 py-2.5 font-medium">{{ $y->mahasiswa->nama }}</td>
                            <td class="px-5 py-2.5 text-ink-muted">{{ $y->mahasiswa->prodi->namaLengkap() }}</td>
                            <td class="tabular px-5 py-2.5 text-center">{{ $y->total_sks }}</td>
                            <td class="tabular px-5 py-2.5 text-center font-semibold">{{ Format::angka($y->ipk) }}</td>
                            <td class="px-5 py-2.5">
                                @if ($y->predikat)
                                    <x-chip tone="gold">{{ $y->predikat }}</x-chip>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="tabular px-5 py-2.5 text-ink-muted">{{ Format::tanggal($y->tanggal_lulus) }}</td>
                            <td class="px-5 py-2.5">
                                @php $pin = $kesiapanPin[$y->mahasiswa->id] ?? null; @endphp
                                @if ($pin === null)
                                    <span class="text-ink-faint">—</span>
                                @elseif ($pin->siap())
                                    <x-chip tone="success">Siap</x-chip>
                                @else
                                    {{-- Which items, not just how many: the operator
                                         fixes these in Feeder, one screen each. --}}
                                    <details class="group">
                                        <summary class="cursor-pointer list-none">
                                            <x-chip tone="warning">{{ $pin->jumlahBelumSiap() }} belum</x-chip>
                                        </summary>
                                        <ul class="mt-2 space-y-1 text-[11.5px] text-ink-muted">
                                            @foreach ($pin->rincian as $r)
                                                @unless ($r['siap'])
                                                    <li>× {{ $r['label'] }} <span class="text-ink-faint">— {{ $r['keterangan'] }}</span></li>
                                                @endunless
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12">
                                <x-empty-state
                                    title="Belum ada lulusan"
                                    :description="__('iku.yudisium_kosong')"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
