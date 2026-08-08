@extends('layouts.app')

@section('title', 'Matriks Tarif')

@php
    $rp = fn ($n) => 'Rp'.number_format((float) $n, 0, ',', '.');
    $dimensi = function ($t) {
        $bagian = [];
        if ($t->prodi) $bagian[] = $t->prodi->nama;
        if ($t->angkatan) $bagian[] = 'angkatan '.$t->angkatan;
        if ($t->jalur_masuk) $bagian[] = 'jalur '.$t->jalur_masuk;
        if ($t->golongan_ukt) $bagian[] = 'gol. '.$t->golongan_ukt;
        return $bagian === [] ? 'berlaku umum' : implode(' · ', $bagian);
    };
@endphp

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5">
        <x-alert tone="info">
            Baris tarif saling menimpa, <strong>tidak dijumlahkan</strong>. Untuk setiap
            komponen, baris yang paling spesifik menang — sehingga satu tarif umum dan
            satu penimpa per prodi dapat hidup berdampingan. Dimensi yang dikosongkan
            berarti “berlaku untuk semua”.
        </x-alert>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex min-w-0 flex-col gap-5">
            {{-- Simulator --}}
            <x-card title="Simulasi Tagihan" meta="lihat baris mana yang menang">
                <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                    @foreach ($filter as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <x-field label="NIM Mahasiswa" name="simulasi_nim" type="search"
                        :value="request('simulasi_nim')" placeholder="Ketik NIM…" class="min-w-[180px] flex-1" />
                    <x-field label="Semester" name="simulasi_term" :value="request('simulasi_term')"
                        :options="$daftarTerm->pluck('nama', 'id')" />
                    <x-button type="submit">Hitung</x-button>
                </form>

                @if ($simulasi === null)
                    <p class="text-[13px] text-ink-muted">
                        Masukkan NIM untuk melihat komponen apa saja yang akan ditagih dan
                        dari baris tarif mana angkanya berasal.
                    </p>
                @elseif ($simulasi['mahasiswa'] === null)
                    <x-alert tone="warning">
                        Mahasiswa dengan NIM {{ $simulasi['nim'] }} tidak ditemukan.
                    </x-alert>
                @elseif (($simulasi['term'] ?? null) === null)
                    <x-alert tone="warning">Pilih semester lebih dulu.</x-alert>
                @else
                    <div class="mb-3.5 rounded-card border border-line bg-canvas px-4 py-3">
                        <div class="font-medium">{{ $simulasi['mahasiswa']->nama }}</div>
                        <div class="tabular text-[12px] text-ink-muted">
                            {{ $simulasi['mahasiswa']->nim }} ·
                            {{ $simulasi['mahasiswa']->prodi->nama }} ·
                            angkatan {{ $simulasi['mahasiswa']->angkatan }}
                            @if ($simulasi['mahasiswa']->golongan_ukt)
                                · golongan UKT {{ $simulasi['mahasiswa']->golongan_ukt }}
                            @else
                                · <span class="text-warning">golongan UKT belum diisi</span>
                            @endif
                        </div>
                    </div>

                    @forelse ($simulasi['rincian'] as $baris)
                        <div class="mb-3 border-b border-line pb-3 last:mb-0 last:border-b-0 last:pb-0">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-muted">
                                        {{ $komponenPilihan[$baris['terpilih']->komponen] ?? $baris['terpilih']->komponen }}
                                    </span>
                                    <div class="text-[13px]">{{ $baris['terpilih']->nama }}</div>
                                    <div class="text-[11.5px] text-ink-faint">{{ $dimensi($baris['terpilih']) }}</div>
                                </div>
                                <div class="tabular text-[15px] font-semibold">{{ $rp($baris['terpilih']->nominal) }}</div>
                            </div>

                            @if ($baris['dikalahkan']->isNotEmpty())
                                <details class="mt-1.5">
                                    <summary class="cursor-pointer text-[11.5px] text-navy">
                                        {{ $baris['dikalahkan']->count() }} baris lain juga cocok, dikalahkan
                                    </summary>
                                    <ul class="mt-1.5 flex flex-col gap-1">
                                        @foreach ($baris['dikalahkan'] as $kalah)
                                            <li class="tabular text-[11.5px] text-ink-faint line-through">
                                                {{ $rp($kalah->nominal) }} — {{ $dimensi($kalah) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>
                    @empty
                        <x-alert tone="danger">
                            Tidak ada tarif yang cocok. Mahasiswa ini <strong>tidak akan
                            ditagih sama sekali</strong> pada penerbitan massal.
                        </x-alert>
                    @endforelse

                    @if ($simulasi['rincian']->isNotEmpty())
                        <div class="mt-3.5 flex items-baseline justify-between border-t-2 border-navy pt-3">
                            <span class="text-[13px] font-semibold">Total</span>
                            <span class="tabular font-serif text-[22px] font-semibold">{{ $rp($simulasi['total']) }}</span>
                        </div>
                    @endif
                @endif
            </x-card>

            {{-- Matriks --}}
            <x-card>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <x-field label="Komponen" name="komponen" :value="$filter['komponen'] ?? ''"
                        :options="$komponenPilihan" />
                    <x-field label="Program Studi" name="prodi" :value="$filter['prodi'] ?? ''"
                        :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />
                    <x-button type="submit">Terapkan</x-button>
                    @if (array_filter($filter))
                        <x-button variant="ghost" :href="route('admin.tarif')">Reset</x-button>
                    @endif
                </form>
            </x-card>

            @forelse ($daftar as $komponen => $baris)
                <x-card :title="$komponenPilihan[$komponen] ?? $komponen" :meta="$baris->count().' baris'" flush>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Nama</th>
                                    <th class="px-5 py-3 font-semibold">Berlaku Untuk</th>
                                    <th class="px-5 py-3 font-semibold">Masa Berlaku</th>
                                    <th class="px-5 py-3 text-right font-semibold">Nominal</th>
                                    <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($baris as $t)
                                    <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            {{ $t->nama }}
                                            @unless ($t->is_active)
                                                <x-chip tone="neutral" class="ml-1">nonaktif</x-chip>
                                            @endunless
                                        </td>
                                        <td class="px-5 py-3 text-ink-muted">
                                            {{ $dimensi($t) }}
                                            <div class="text-[11px] text-ink-faint">
                                                spesifisitas {{ $t->spesifisitas() }}
                                            </div>
                                        </td>
                                        <td class="tabular px-5 py-3 text-ink-muted">
                                            @if ($t->term_berlaku_dari || $t->term_berlaku_sampai)
                                                {{ $t->term_berlaku_dari ?? '…' }} – {{ $t->term_berlaku_sampai ?? '…' }}
                                            @else
                                                <span class="text-ink-faint">tanpa batas</span>
                                            @endif
                                        </td>
                                        <td class="tabular px-5 py-3 text-right font-semibold">{{ $rp($t->nominal) }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <form method="POST" action="{{ route('admin.tarif.hapus', $t) }}"
                                                onsubmit="return confirm('Nonaktifkan baris tarif ini? Tagihan yang sudah terbit tidak berubah.');">
                                                @csrf @method('DELETE')
                                                <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @empty
                <x-card>
                    <x-empty-state title="Matriks tarif masih kosong"
                        description="Tanpa tarif, penerbitan tagihan massal melaporkan seluruh mahasiswa sebagai “tanpa tarif” dan tidak menagih siapa pun." />
                </x-card>
            @endforelse
        </div>

        <x-card title="Baris Tarif Baru">
            <form method="POST" action="{{ route('admin.tarif.store') }}" class="flex flex-col gap-3.5">
                @csrf

                <x-field label="Komponen" name="komponen" required :options="$komponenPilihan" />
                <x-field label="Nama" name="nama" required placeholder="UKT Semester Ganjil" />
                <x-field label="Nominal (Rp)" name="nominal" type="number" required :value="0" />

                <div class="mt-1 border-t border-line pt-3">
                    <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        Berlaku Untuk
                    </p>
                    <p class="mb-3 text-[11.5px] leading-relaxed text-ink-faint">
                        Kosongkan berarti berlaku untuk semua. Makin banyak yang diisi,
                        makin spesifik, dan makin menang atas baris yang lebih umum.
                    </p>

                    <div class="flex flex-col gap-3">
                        <x-field label="Program Studi" name="prodi_id"
                            :options="$daftarProdi->mapWithKeys(fn ($p) => [$p->id => $p->namaLengkap()])" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field label="Angkatan" name="angkatan" type="number" />
                            <x-field label="Golongan UKT" name="golongan_ukt" :options="$golonganPilihan" />
                        </div>
                        <x-field label="Jalur Masuk" name="jalur_masuk" placeholder="Reguler" />
                    </div>
                </div>

                <div class="mt-1 border-t border-line pt-3">
                    <p class="mb-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        Masa Berlaku
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="Dari (kode)" name="term_berlaku_dari" placeholder="20261" />
                        <x-field label="Sampai (kode)" name="term_berlaku_sampai" placeholder="20272" />
                    </div>
                    <p class="mt-1.5 text-[11.5px] text-ink-faint">
                        Kode semester PDDIKTI. Kosongkan untuk berlaku tanpa batas waktu.
                    </p>
                </div>

                <x-button type="submit" class="mt-1 w-full">Tambah Baris</x-button>
            </form>
        </x-card>
    </div>
@endsection
