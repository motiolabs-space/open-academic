@extends('layouts.app')

@section('title', 'Verifikasi Dua Langkah')

@section('content')
    @if ($aktif)
        <x-card class="mb-5" title="Verifikasi Dua Langkah" meta="Aktif">
            <div class="flex flex-wrap items-center gap-4">
                <x-chip tone="success">Aktif</x-chip>
                <span class="text-[13px] text-ink-muted">
                    Kode pemulihan tersisa: <strong class="tabular">{{ $sisaPemulihan }}</strong> dari 8
                </span>
            </div>

            @if ($sisaPemulihan <= 2)
                {{-- Peringatan sebelum habis, bukan sesudah: kode pemulihan
                     terakhir yang terpakai berarti ponsel hilang berikutnya
                     tidak punya jalan keluar sama sekali. --}}
                <x-alert tone="warning" class="mt-4">
                    Kode pemulihan Anda hampir habis. Buat yang baru sekarang, selagi Anda
                    masih bisa masuk.
                </x-alert>
            @endif

            <div class="mt-5 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('dua-faktor.pemulihan') }}">
                    @csrf
                    <x-button variant="outline" type="submit" class="px-4 py-2 text-xs">
                        Buat kode pemulihan baru
                    </x-button>
                </form>
            </div>
        </x-card>
    @else
        <x-card class="mb-5" title="Verifikasi Dua Langkah" meta="Belum aktif">
            <p class="text-[13.5px] leading-relaxed text-ink-muted">
                Akun staf dapat mengubah nilai, menghapus tagihan, dan menerbitkan kelulusan.
                Satu kata sandi yang bocor di sana tidak membocorkan catatan — ia
                <strong>mengubahnya</strong>, dan kelulusan yang terlanjur terbit tidak dapat
                ditarik pulang dengan mengganti kata sandi.
            </p>

            @if (! $rahasia)
                <x-button :href="route('dua-faktor.kelola', ['pasang' => 1])" class="mt-5">
                    Pasang sekarang
                </x-button>
            @endif
        </x-card>
    @endif

    {{-- ============ PEMASANGAN ============ --}}
    @if ($rahasia && ! $aktif)
        <x-card class="mb-5" title="Langkah Pemasangan" flush>
            <div class="grid gap-6 px-5 py-5 md:grid-cols-[190px_1fr]">
                <div>
                    @if ($qr)
                        <img src="{{ $qr }}" alt="Kode QR pemasangan" width="190" height="190"
                             class="rounded border border-line bg-white p-2" />
                    @endif
                </div>

                <div>
                    <ol class="list-decimal space-y-3 pl-5 text-[13.5px] leading-relaxed text-ink-muted">
                        <li>
                            Pasang aplikasi autentikator di ponsel — Google Authenticator,
                            Microsoft Authenticator, atau Authy.
                        </li>
                        <li>Pindai kode QR di samping.</li>
                        <li>
                            Bila kamera tidak dapat dipakai, masukkan kunci ini secara manual:
                            <code class="tabular mt-1 block break-all rounded bg-surface-muted px-2 py-1.5 text-[12.5px]">{{ $rahasia }}</code>
                        </li>
                        <li>Ketik enam angka yang muncul, untuk membuktikan pemasangannya berhasil.</li>
                    </ol>

                    <form method="POST" action="{{ route('dua-faktor.konfirmasi') }}" class="mt-5 flex flex-wrap items-end gap-3">
                        @csrf

                        <div>
                            <label for="kode" class="mb-1 block text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                                Kode dari aplikasi
                            </label>
                            <input id="kode" name="kode" type="text" inputmode="numeric"
                                   autocomplete="one-time-code" required maxlength="32"
                                   class="tabular w-40 rounded border border-line bg-surface px-3 py-2 text-[15px] tracking-[0.2em]"
                                   placeholder="000000" />
                        </div>

                        <x-button type="submit" class="px-5 py-2">Aktifkan</x-button>
                    </form>

                    <p class="mt-3 text-[12px] text-ink-faint">
                        Belum aktif sampai satu kode berhasil diketik. Sampai saat itu akun Anda
                        tetap masuk dengan kata sandi saja — sehingga memindai QR lalu kehilangan
                        ponsel tidak mengunci Anda di luar.
                    </p>
                </div>
            </div>
        </x-card>
    @endif

    {{-- ============ KODE PEMULIHAN ============ --}}
    @if ($kodePemulihan)
        <x-card class="mb-5" title="Kode Pemulihan" meta="Ditampilkan sekali saja">
            <x-alert tone="warning">
                <strong>Salin sekarang.</strong> Kode ini tidak akan ditampilkan lagi — yang
                tersimpan di basis data hanya sidik jarinya, bukan kodenya. Simpan di tempat
                yang bukan ponsel Anda.
            </x-alert>

            <div class="tabular mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($kodePemulihan as $kode)
                    <code class="rounded bg-surface-muted px-3 py-2 text-[13px]">{{ $kode }}</code>
                @endforeach
            </div>

            <p class="mt-4 text-[12.5px] leading-relaxed text-ink-muted">
                Setiap kode berlaku satu kali. Pakai bila ponsel hilang atau aplikasi
                autentikatornya terhapus.
            </p>
        </x-card>
    @endif

    {{-- ============ MATIKAN ============ --}}
    @if ($aktif && ! $wajib)
        <x-card title="Matikan Verifikasi Dua Langkah">
            <p class="text-[13px] leading-relaxed text-ink-muted">
                Kata sandi diminta lagi di sini dengan sengaja: tanpa itu, peramban yang
                ditinggalkan terbuka sudah cukup untuk mencabut faktor kedua — dan seluruh
                pengamanannya jadi sekadar hiasan.
            </p>

            <form method="POST" action="{{ route('dua-faktor.matikan') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf

                <div>
                    <label for="password" class="mb-1 block text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                        Kata sandi Anda
                    </label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="w-56 rounded border border-line bg-surface px-3 py-2 text-[13px]" />
                </div>

                <x-button variant="outline" type="submit" class="px-4 py-2 text-xs">Matikan</x-button>
            </form>
        </x-card>
    @elseif ($aktif && $wajib)
        <x-card title="Matikan Verifikasi Dua Langkah">
            <p class="text-[13px] leading-relaxed text-ink-muted">
                Kampus ini mewajibkan verifikasi dua langkah untuk akun staf
                (<code>DUA_FAKTOR_WAJIB</code>), jadi ia tidak dapat dimatikan sendiri.
            </p>
        </x-card>
    @endif
@endsection
