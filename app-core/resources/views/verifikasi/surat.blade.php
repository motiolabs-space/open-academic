@extends('layouts.base')

@section('title', 'Verifikasi Dokumen')

@php
    use App\Services\Branding\BrandingService;
    use App\Support\Format;

    $brand = app(BrandingService::class);
@endphp

@section('body')
    {{-- Halaman untuk orang luar: petugas bank, staf kedutaan, calon pemberi
         kerja. Mereka tidak punya akun di sini dan tidak akan membuatnya, jadi
         halaman ini berdiri sendiri dan menjelaskan dirinya sendiri. --}}
    <div class="min-h-screen bg-canvas">
        <header class="border-b border-line bg-navy px-6 py-5 text-canvas">
            <div class="mx-auto flex max-w-3xl items-center gap-3">
                <div class="grid h-9 w-9 place-items-center rounded-lg border-[1.5px] border-gold font-serif text-lg font-bold text-gold">
                    {{ $brand->logoInitial() }}
                </div>
                <div>
                    <div class="text-[13px] font-semibold">{{ $brand->institutionName() }}</div>
                    <div class="text-[11px] uppercase tracking-[0.14em] text-canvas/70">Verifikasi Dokumen</div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-8">
            @if ($laporan !== null)
                @php
                    $tone = $laporan['berlaku'] ? 'success' : ($laporan['dicabut'] ? 'danger' : 'warning');
                @endphp

                <x-card>
                    <div class="flex flex-wrap items-center gap-3 border-b border-line pb-4">
                        <span @class([
                            'grid h-11 w-11 shrink-0 place-items-center rounded-full text-xl',
                            'bg-success-bg text-success-ink' => $laporan['berlaku'],
                            'bg-danger-bg text-danger' => $laporan['dicabut'],
                            'bg-warning-bg text-warning-ink' => ! $laporan['berlaku'] && ! $laporan['dicabut'],
                        ]) aria-hidden="true">
                            {{ $laporan['berlaku'] ? '✓' : ($laporan['dicabut'] ? '✕' : '!') }}
                        </span>

                        <div class="min-w-0">
                            <h1 class="font-serif text-lg font-semibold">
                                Dokumen ini terdaftar dan asli
                            </h1>
                            {{-- Kalimat inilah yang sebenarnya dipakai pembaca.
                                 "Asli" saja menyesatkan untuk surat yang
                                 menyatakan suatu keadaan: bisa jadi otentik dan
                                 sekaligus sudah tidak menggambarkan apa pun. --}}
                            <p class="mt-1 text-[13px] text-ink-muted">{{ $laporan['catatan'] }}</p>
                        </div>

                        <div class="ml-auto"><x-chip :tone="$tone" dot>
                            {{ $laporan['berlaku'] ? 'Berlaku' : ($laporan['dicabut'] ? 'Dicabut' : 'Kedaluwarsa') }}
                        </x-chip></div>
                    </div>

                    <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ([
                            'Jenis Dokumen' => $laporan['jenis'],
                            'Nomor' => $laporan['nomor'],
                            'Atas Nama' => $laporan['nama'],
                            'NIM' => $laporan['nim'],
                            'Program Studi' => $laporan['prodi'],
                            'Penerbit' => $laporan['institusi'],
                            'Tanggal Terbit' => Format::tanggalPanjang($laporan['diterbitkan']),
                        ] as $label => $nilai)
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ $label }}</dt>
                                <dd class="mt-0.5 text-[13.5px]">{{ $nilai ?? '—' }}</dd>
                            </div>
                        @endforeach

                        @if ($laporan['berlaku_sampai'])
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Berlaku Sampai</dt>
                                <dd class="mt-0.5 text-[13.5px] {{ $laporan['kedaluwarsa'] ? 'font-semibold text-danger' : '' }}">
                                    {{ Format::tanggalPanjang($laporan['berlaku_sampai']) }}
                                </dd>
                            </div>
                        @endif

                        @if ($laporan['dicabut_pada'])
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">Dicabut Pada</dt>
                                <dd class="mt-0.5 text-[13.5px] font-semibold text-danger">
                                    {{ Format::tanggalPanjang($laporan['dicabut_pada']) }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <p class="mt-5 border-t border-line pt-4 text-[12px] text-ink-faint">
                        Cocokkan data di atas dengan dokumen yang Anda pegang. Bila ada yang berbeda —
                        nama, nomor, atau tanggal — dokumen tersebut bukan yang terdaftar pada nomor ini.
                    </p>
                </x-card>
            @elseif ($dicari !== null)
                <x-card>
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-danger-bg text-xl text-danger" aria-hidden="true">✕</span>
                        <div>
                            <h1 class="font-serif text-lg font-semibold">Dokumen tidak ditemukan</h1>
                            <p class="mt-1 text-[13px] text-ink-muted">
                                Tidak ada dokumen terdaftar dengan nomor <strong>{{ $dicari }}</strong>
                                atas NIM yang dimasukkan. Periksa kembali keduanya — keduanya tercetak
                                pada dokumen.
                            </p>
                        </div>
                    </div>
                </x-card>
            @endif

            <x-card title="Periksa Dokumen Lain" class="{{ $laporan !== null || $dicari !== null ? 'mt-5' : '' }}">
                <p class="mb-4 text-[13px] text-ink-muted">
                    Pindai kode QR pada dokumen, atau masukkan nomor surat beserta NIM yang tercetak
                    di dalamnya.
                </p>

                <form method="POST" action="{{ route('verifikasi.cari') }}" class="grid gap-3 sm:grid-cols-[1fr_200px_auto]">
                    @csrf
                    <x-field label="Nomor Surat" name="nomor" :value="$dicari" required
                        placeholder="0001/SKAK/UND/VIII/2026" />
                    <x-field label="NIM" name="nim" required />
                    <div class="flex items-end"><x-button type="submit">Periksa</x-button></div>
                </form>
            </x-card>

            <p class="mt-6 text-center text-[11.5px] text-ink-faint">
                Halaman ini hanya menampilkan data secukupnya untuk mencocokkan dokumen.
                Data pribadi lain tidak ditampilkan.
            </p>
        </main>
    </div>
@endsection
