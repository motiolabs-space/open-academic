@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card title="Identitas Institusi">
            <form method="POST" action="{{ route('admin.pengaturan.simpan') }}" class="flex flex-col gap-3.5">
                @csrf @method('PUT')

                <x-field label="Nama Institusi" name="institution_name" required
                    :value="$branding['institution_name'] ?? ''" />
                <x-field label="Singkatan" name="institution_short" required
                    :value="$branding['institution_short'] ?? ''"
                    hint="Muncul pada judul tab dan kop transkrip." />
                <x-field label="Kode PDDIKTI Institusi" name="institution_code"
                    :value="$branding['institution_code'] ?? ''"
                    hint="Salah isi berarti seluruh pelaporan kampus ini masuk atas nama institusi lain." />

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-field label="Warna Utama" name="primary_color" required
                            :value="$branding['primary_color'] ?? '#1E2761'" placeholder="#1E2761" />
                        <div class="mt-1.5 h-6 rounded border border-line"
                            style="background: {{ $branding['primary_color'] ?? '#1E2761' }}"></div>
                    </div>
                    <div>
                        <x-field label="Warna Aksen" name="accent_color" required
                            :value="$branding['accent_color'] ?? '#C9A961'" placeholder="#C9A961" />
                        <div class="mt-1.5 h-6 rounded border border-line"
                            style="background: {{ $branding['accent_color'] ?? '#C9A961' }}"></div>
                    </div>
                </div>

                <x-button type="submit" class="mt-1 self-start">Simpan Pengaturan</x-button>
            </form>
        </x-card>

        <x-card title="Aturan Akademik" meta="hanya dapat diubah lewat berkas konfigurasi">
            <p class="mb-4 text-[12.5px] leading-relaxed text-ink-muted">
                Aturan berikut menentukan bagaimana angka <em>dihitung</em>, bukan sekadar
                bagaimana angka ditampilkan. Karena itu ia hidup di
                <code class="rounded bg-line/50 px-1.5 py-0.5 text-[11.5px]">config/academic.php</code>
                dan ikut kendali versi — mengubah skala nilai di tengah semester akan
                menulis ulang transkrip yang sudah terlanjur terbit, tanpa jejak.
            </p>

            <dl class="flex flex-col gap-3">
                @foreach ($aturanTerkunci as $label => $nilai)
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line pb-2.5 last:border-b-0">
                        <dt class="text-[12.5px] font-semibold">{{ $label }}</dt>
                        <dd class="tabular text-[12.5px] text-ink-muted">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>
    </div>

    <x-card class="mt-5" title="Dokumen Cetak" meta="kop, penandatangan, dan catatan kaki">
        <p class="mb-4 text-[12.5px] leading-relaxed text-ink-muted">
            Yang dapat diubah di sini adalah <em>isi</em>: baris kop, siapa yang bertanda
            tangan, dan catatan di kaki halaman. Tata letaknya tetap di berkas Blade dan
            ikut kendali versi — templat yang dapat disunting dari layar berarti
            mengeksekusi kode yang tersimpan di basis data, dan keluwesannya tidak
            sebanding dengan itu.
        </p>

        <form method="POST" action="{{ route('admin.pengaturan.dokumen') }}" class="flex flex-col gap-5">
            @csrf @method('PUT')

            <div class="grid gap-3.5 sm:grid-cols-2">
                <x-field label="Alamat pada kop" name="kop_alamat"
                    :value="$dokumen->first()['nilai']['alamat'] ?? ''"
                    hint="Satu baris di bawah nama institusi." />
                <x-field label="Kontak pada kop" name="kop_kontak"
                    :value="$dokumen->first()['nilai']['kontak'] ?? ''"
                    hint="Telepon, surel, atau laman." />
            </div>

            @foreach ($dokumen as $jenis)
                <div class="border-t border-line pt-4">
                    <h3 class="mb-3 text-[13px] font-semibold">{{ $jenis['label'] }}</h3>

                    <div class="grid gap-3.5 sm:grid-cols-2">
                        <x-field label="Judul tercetak" :name="$jenis['jenis'].'_judul'"
                            :value="$jenis['nilai']['judul']" />
                        <x-field label="Catatan kaki" :name="$jenis['jenis'].'_catatan_kaki'"
                            :value="$jenis['nilai']['catatan_kaki']" />
                    </div>

                    @if ($jenis['bertanda_tangan'])
                        <div class="mt-3.5 grid gap-3.5 sm:grid-cols-3">
                            <x-field label="Nama penandatangan" :name="$jenis['jenis'].'_ttd_nama'"
                                :value="$jenis['nilai']['penandatangan']['nama']" />
                            <x-field label="Jabatan" :name="$jenis['jenis'].'_ttd_jabatan'"
                                :value="$jenis['nilai']['penandatangan']['jabatan']" />
                            <x-field label="NIP" :name="$jenis['jenis'].'_ttd_nip'"
                                :value="$jenis['nilai']['penandatangan']['nip']" />
                        </div>
                    @else
                        {{-- Ditandatangani di kertas oleh yang hadir. Nama tercetak di
                             sini justru salah: yang dibutuhkan ruang kosong, bukan
                             pejabat yang tidak ada di ruangan itu. --}}
                        <p class="mt-3 text-[12px] text-ink-faint">
                            Ditandatangani di kertas oleh dosen pengampu, jadi tidak ada
                            penandatangan tetap yang dicetak.
                        </p>
                    @endif
                </div>
            @endforeach

            <x-button type="submit" class="self-start">Simpan Pengaturan Dokumen</x-button>
        </form>
    </x-card>
@endsection
