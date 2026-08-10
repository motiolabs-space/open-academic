@extends('layouts.app')

@section('title', 'Rencana Kinerja')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <x-card class="mb-5" title="Periode">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <label class="flex flex-col gap-1">
                <span class="text-[11px] font-semibold text-ink-muted">Pilih periode</span>
                <select name="periode" class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                    @foreach ($daftarPeriode as $p)
                        <option value="{{ $p->uuid }}" @selected($periode?->id === $p->id)>
                            {{ $p->nama }} — {{ $p->status->label() }}
                        </option>
                    @endforeach
                </select>
            </label>
            <x-button type="submit" variant="outline" class="px-4 py-2 text-xs">Tampilkan</x-button>
        </form>

        @if ($periode)
            <div class="flex flex-wrap items-center gap-2 border-t border-line pt-3">
                <x-chip :tone="$periode->status->tone()">{{ $periode->status->label() }}</x-chip>
                <span class="tabular text-[12px] text-ink-muted">
                    {{ $periode->mulai->translatedFormat('d M Y') }} –
                    {{ $periode->selesai->translatedFormat('d M Y') }}
                </span>

                @if ($periode->status->dapatDiubah())
                    <div class="ml-auto flex flex-wrap gap-2">
                        @if ($periode->status === App\Enums\StatusPeriodeKinerja::Draf)
                            <form method="POST" action="{{ route('admin.kinerja.jalankan', $periode) }}">
                                @csrf
                                <x-button type="submit" class="px-3 py-1.5 text-xs">Jalankan</x-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.kinerja.ukur', $periode) }}">
                                @csrf
                                <x-button type="submit" variant="outline" class="px-3 py-1.5 text-xs">
                                    Ukur ulang dari data
                                </x-button>
                            </form>

                            {{-- Penguncian searah, dan layarnya mengatakan itu
                                 sebelum terjadi. Periode yang dapat dibuka lagi
                                 adalah periode yang angkanya dapat direvisi
                                 sesudah dilaporkan. --}}
                            <form method="POST" action="{{ route('admin.kinerja.kunci', $periode) }}"
                                onsubmit="return confirm('Kunci periode ini? Target dan realisasinya dibekukan permanen dan tidak dapat dibuka lagi.')">
                                @csrf
                                <x-button type="submit" variant="outline" class="px-3 py-1.5 text-xs">
                                    Kunci periode
                                </x-button>
                            </form>
                        @endif
                    </div>
                @else
                    <span class="ml-auto text-[12px] text-ink-faint">
                        Dikunci {{ $periode->dikunci_at?->translatedFormat('d M Y') }}
                        oleh {{ $periode->dikunciOleh?->nama ?? '—' }}
                    </span>
                @endif
            </div>
        @endif
    </x-card>

    <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">

        {{-- ============ POHON SASARAN ============ --}}
        <x-card title="Sasaran & Ukuran" :meta="$pohon->count().' sasaran'" flush>
            <div class="divide-y divide-line/60">
                @forelse ($pohon as $sasaran)
                    <div class="px-5 py-4" style="padding-left: {{ 20 + ($kedalaman[$sasaran->id] ?? 0) * 18 }}px">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[13.5px] font-semibold">{{ $sasaran->judul }}</span>
                            <x-chip tone="neutral">{{ $sasaran->unit?->nama }}</x-chip>
                        </div>

                        {{-- Penanggung jawab diturunkan dari kepala unit saat ini,
                             tidak disimpan: dekan berganti, sasaran tidak ikut
                             pindah ke mantan dekan. --}}
                        <div class="text-[11.5px] text-ink-faint">
                            Penanggung jawab: {{ $sasaran->penanggungJawab()?->nama ?? 'belum ada kepala unit' }}
                        </div>

                        @forelse ($sasaran->ukuran as $ukuran)
                            @php $status = $ukuran->statusCapaian(); @endphp
                            <div class="mt-2 flex flex-wrap items-baseline gap-2 border-l-2 border-line pl-3">
                                <span class="text-[12.5px]">{{ $ukuran->nama }}</span>
                                <x-chip :tone="$ukuran->sumber_realisasi->tone()">
                                    {{ $ukuran->sumber_realisasi->label() }}
                                </x-chip>

                                <span class="tabular text-[12px] text-ink-muted">
                                    {{ $ukuran->realisasi() === null ? 'belum terukur' : $ukuran->realisasi() }}
                                    / {{ $ukuran->targetBerlaku() }} {{ $ukuran->satuan }}
                                </span>

                                @if ($status)
                                    <x-chip :tone="$status['tone']">
                                        {{ $status['sebutan'] }} · {{ $ukuran->persenCapaian() }}%
                                    </x-chip>
                                @endif

                                @if ($ukuran->beku())
                                    <x-chip tone="neutral">beku</x-chip>
                                @endif
                            </div>

                            @if ($ukuran->sumber_realisasi->bolehDiketik() && $periode?->status->menerimaCapaian())
                                <form method="POST" action="{{ route('admin.kinerja.capaian', $ukuran) }}"
                                    class="mt-1.5 flex flex-wrap items-end gap-2 pl-3">
                                    @csrf
                                    <input type="number" step="0.01" name="nilai" required placeholder="Nilai"
                                        class="tabular w-24 rounded border border-line bg-canvas px-2 py-1 text-[12px]">
                                    <input type="date" name="tanggal" required value="{{ now()->toDateString() }}"
                                        class="rounded border border-line bg-canvas px-2 py-1 text-[12px]">
                                    <x-button type="submit" variant="ghost" class="px-3 py-1 text-xs">Catat</x-button>
                                </form>
                            @endif
                        @empty
                            <div class="mt-2 text-[12px] text-ink-faint">Belum ada ukuran.</div>
                        @endforelse

                        @if ($periode?->status->dapatDiubah())
                            <details class="mt-2">
                                <summary class="cursor-pointer text-[11.5px] text-ink-muted">+ ukuran</summary>
                                <form method="POST" action="{{ route('admin.kinerja.ukuran', $sasaran) }}"
                                    class="mt-2 grid gap-2 sm:grid-cols-2">
                                    @csrf
                                    <x-field label="Nama ukuran" name="nama" required />

                                    <label class="flex flex-col gap-1">
                                        <span class="text-[11px] font-semibold text-ink-muted">Sumber realisasi</span>
                                        <select name="sumber_realisasi" required
                                            class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                                            @foreach ($sumberOptions as $nilai => $label)
                                                <option value="{{ $nilai }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="flex flex-col gap-1">
                                        <span class="text-[11px] font-semibold text-ink-muted">
                                            Indikator (wajib bila dihitung)
                                        </span>
                                        <select name="indikator_kunci"
                                            class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                                            <option value="">—</option>
                                            @foreach ($indikator as $kunci => $def)
                                                <option value="{{ $kunci }}">{{ $def['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <x-field label="Target" name="target" type="number" step="0.01" required />

                                    <label class="flex items-center gap-2 sm:col-span-2">
                                        <input type="checkbox" name="semakin_besar_semakin_baik" value="1" checked
                                            class="rounded border-line">
                                        <span class="text-[12px]">Makin besar makin baik</span>
                                    </label>

                                    <div class="sm:col-span-2">
                                        <x-button type="submit" variant="outline" class="px-3 py-1.5 text-xs">
                                            Tambah Ukuran
                                        </x-button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ink-muted">
                        Belum ada sasaran pada periode ini.
                    </div>
                @endforelse
            </div>
        </x-card>

        <div class="flex flex-col gap-5">
            @if ($periode?->status->dapatDiubah())
                <x-card title="Tambah Sasaran">
                    <form method="POST" action="{{ route('admin.kinerja.sasaran', $periode) }}"
                        class="flex flex-col gap-3">
                        @csrf

                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-semibold text-ink-muted">Unit pemilik</span>
                            <select name="unit_kerja_id" required
                                class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                                @foreach ($unitAktif as $u)
                                    <option value="{{ $u->id }}">{{ $u->nama }}</option>
                                @endforeach
                            </select>
                        </label>

                        <x-field label="Judul sasaran" name="judul" required />

                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-semibold text-ink-muted">Turunan dari</span>
                            <select name="parent_id"
                                class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                                <option value="">— sasaran teratas —</option>
                                @foreach ($pohon as $s)
                                    <option value="{{ $s->id }}">{{ $s->judul }}</option>
                                @endforeach
                            </select>
                        </label>

                        <x-button type="submit" class="self-start px-4 py-2 text-xs">Tambah Sasaran</x-button>
                    </form>
                </x-card>
            @endif

            <x-card title="Periode Baru">
                <form method="POST" action="{{ route('admin.kinerja.periode') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <x-field label="Nama" name="nama" required />
                    <x-field label="Tahun" name="tahun" type="number" required :value="now()->year" />
                    <x-field label="Mulai" name="mulai" type="date" required />
                    <x-field label="Selesai" name="selesai" type="date" required />

                    <label class="flex flex-col gap-1 sm:col-span-2">
                        <span class="text-[11px] font-semibold text-ink-muted">Semester (opsional)</span>
                        <select name="tahun_akademik_id"
                            class="rounded border border-line bg-canvas px-2.5 py-1.5 text-[12.5px]">
                            <option value="">— periode tahunan —</option>
                            @foreach ($daftarTerm as $t)
                                <option value="{{ $t->id }}">{{ $t->nama }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="sm:col-span-2">
                        <x-button type="submit" variant="outline" class="px-4 py-2 text-xs">Buat Periode</x-button>
                    </div>
                </form>
            </x-card>

            <x-card title="Batas Modul Ini" meta="docs/KINERJA.md">
                <p class="text-[12px] leading-relaxed text-ink-muted">
                    Ini <strong>bukan</strong> dasbor IKU dan bukan SPMI — keduanya milik Open Campus,
                    karena empat dari delapan IKU bersumber di luar aplikasi ini.
                    Yang ada di sini hanya indikator yang realisasinya dapat
                    <strong>dihitung dari data yang aplikasi ini miliki</strong>.
                </p>
                <p class="mt-2 text-[12px] leading-relaxed text-ink-muted">
                    Ukuran bersumber <em>dihitung</em> tidak dapat diketik dari layar mana pun —
                    bukan dilarang izin, melainkan tidak ada jalurnya.
                </p>
            </x-card>
        </div>
    </div>
@endsection
