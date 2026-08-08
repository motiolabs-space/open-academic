@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', 'Konversi Kredit')

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
            <x-field label="Cari mahasiswa" name="cari" :value="$cari" placeholder="Nama atau NIM" />
            <div class="flex items-end"><x-button type="submit">Cari</x-button></div>
        </form>

        @if ($hasilCari->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                @foreach ($hasilCari as $m)
                    <x-button href="{{ route('admin.konversi', ['mahasiswa' => $m->uuid]) }}"
                        variant="outline" size="sm">
                        {{ $m->nama }} · {{ $m->nim }}
                    </x-button>
                @endforeach
            </div>
        @endif
    </x-card>

    @if ($mahasiswa === null)
        <x-empty-state title="Pilih mahasiswa"
            description="Konversi kredit diputuskan per mahasiswa: satu transkrip asal dinilai sekaligus, dengan satu batas yang berlaku untuk seluruh isinya." />
    @else
        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <x-card :title="$mahasiswa->nama" :meta="$mahasiswa->nim.' · '.$mahasiswa->prodi->nama">
                    {{-- Batas ditampilkan sebelum memutuskan, bukan setelah ditolak.
                         Penilai sedang memetakan satu transkrip utuh; angka inilah
                         yang membatasi seluruh isinya. --}}
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-stat-card label="Sudah diakui" :value="$sudah" meta="SKS" />
                        <x-stat-card label="Batas pengakuan" :value="$batas" meta="SKS" />
                        <x-stat-card label="Sisa" :value="$sisa" meta="SKS"
                            :feature="$sisa > 0" />
                    </div>

                    <p class="mt-3 text-[12px] text-ink-muted">
                        Batas {{ config('academic.konversi.maks_persen') }}% dari syarat kelulusan
                        program studi. Tanpa batas, seseorang dapat diakui masuk ke dalam gelar.
                    </p>
                </x-card>

                <x-card flush title="Riwayat Konversi">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                    <th class="px-5 py-3 font-semibold">Mata Kuliah di Sini</th>
                                    <th class="px-5 py-3 font-semibold">Asal</th>
                                    <th class="px-5 py-3 text-center font-semibold">Diakui</th>
                                    <th class="px-5 py-3 font-semibold">Status</th>
                                    <th class="px-5 py-3 text-right font-semibold">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($daftar as $k)
                                    <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                        <td class="px-5 py-3">
                                            <div class="font-medium">{{ $k->mataKuliah->kode }}</div>
                                            <div class="text-[12px] text-ink-muted">{{ $k->mataKuliah->nama }}</div>
                                            <div class="text-[11px] text-ink-faint">{{ $k->mataKuliah->sks }} SKS di kurikulum</div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div>{{ $k->asal_nama }}</div>
                                            <div class="text-[11.5px] text-ink-faint">
                                                {{ $k->asal_institusi ?? $k->jenis->label() }}
                                                @if ($k->asal_sks) · {{ $k->asal_sks }} SKS @endif
                                                @if ($k->asal_nilai) · nilai {{ $k->asal_nilai }} @endif
                                            </div>
                                        </td>
                                        <td class="tabular px-5 py-3 text-center">
                                            {{ $k->sks_diakui }}
                                            <div class="text-[11px] text-ink-faint">{{ $k->nilai_huruf ?? 'tanpa nilai' }}</div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <x-chip :tone="$k->status->tone()">{{ $k->status->label() }}</x-chip>
                                            @if ($k->catatan)
                                                <p class="mt-1 max-w-xs text-[12px] text-ink-muted">{{ $k->catatan }}</p>
                                            @endif
                                            @if ($k->pemutus)
                                                <div class="text-[11px] text-ink-faint">
                                                    {{ $k->pemutus->nama }} · {{ Format::tanggal($k->diputus_at) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex flex-wrap justify-end gap-1.5">
                                                @if ($k->status === \App\Enums\StatusKonversi::Diajukan)
                                                    <form method="POST" action="{{ route('admin.konversi.setujui', $k) }}"
                                                        class="flex items-end gap-1.5">
                                                        @csrf
                                                        <x-field label="SKS" name="sks_diakui" type="number"
                                                            class="w-20" :value="$k->sks_diakui" required />
                                                        <x-field label="Huruf" name="nilai_huruf" class="w-24"
                                                            hint="Boleh kosong" />
                                                        <x-button type="submit" size="sm">Akui</x-button>
                                                    </form>

                                                    <form method="POST" action="{{ route('admin.konversi.tolak', $k) }}"
                                                        class="flex items-end gap-1.5">
                                                        @csrf
                                                        <x-field label="Alasan" name="alasan" class="w-40" required />
                                                        <x-button type="submit" variant="outline" size="sm">Tolak</x-button>
                                                    </form>
                                                @elseif ($k->status === \App\Enums\StatusKonversi::Disetujui)
                                                    <form method="POST" action="{{ route('admin.konversi.cabut', $k) }}"
                                                        class="flex items-end gap-1.5">
                                                        @csrf
                                                        <x-field label="Alasan cabut" name="alasan" class="w-40" required />
                                                        <x-button type="submit" variant="outline" size="sm">Cabut</x-button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <x-empty-state title="Belum ada konversi"
                                                description="Catat mata kuliah yang sudah ditempuh di tempat lain lewat panel di samping." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>

            <div class="space-y-5">
                <x-card title="Usulkan Konversi">
                    <form method="POST" action="{{ route('admin.konversi.ajukan', $mahasiswa) }}"
                        enctype="multipart/form-data" class="space-y-3">
                        @csrf

                        <x-field label="Mata kuliah di sini" name="mata_kuliah_id" required
                            :options="$mataKuliah->mapWithKeys(fn ($m) => [$m->id => $m->kode.' — '.$m->nama.' ('.$m->sks.' SKS)'])" />

                        <x-field label="Jenis" name="jenis" :options="$jenisPilihan" required />

                        <x-field label="Nama asal" name="asal_nama" required
                            hint="Nama mata kuliah asal, atau uraian pengalaman untuk RPL." />

                        <x-field label="Perguruan tinggi asal" name="asal_institusi"
                            hint="Wajib untuk transfer. Kosongkan untuk RPL dari pengalaman kerja." />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field label="SKS asal" name="asal_sks" type="number" />
                            <x-field label="Nilai asal" name="asal_nilai" />
                        </div>

                        <x-field label="Bukti" name="berkas" type="file"
                            hint="Transkrip asal atau surat keterangan. Konversi tanpa bukti hanyalah klaim." />

                        <x-button type="submit" class="w-full">Catat usulan</x-button>
                    </form>
                </x-card>

                <x-card title="Catatan">
                    <p class="text-[13px] leading-relaxed text-ink-muted">
                        Mata kuliah yang sudah dikonversi <strong>tidak dapat diambil lagi</strong> di KRS,
                        dan mata kuliah yang sudah ditempuh di sini tidak dapat dikonversi. Keduanya
                        mencegah kredit terhitung dua kali.
                    </p>
                    <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">
                        Nilai konversi
                        <strong>{{ config('academic.konversi.hitung_ipk') ? 'ikut dihitung' : 'tidak dihitung' }}</strong>
                        ke dalam IPK pada instalasi ini, tetapi selalu masuk ke total SKS.
                    </p>
                </x-card>
            </div>
        </div>
    @endif
@endsection
