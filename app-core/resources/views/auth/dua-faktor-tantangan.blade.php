@extends('layouts.base')

@section('title', 'Verifikasi Dua Langkah')

@php
    use App\Services\Branding\BrandingService;
    $brand = app(BrandingService::class);
@endphp

@section('body')
<div class="grid min-h-screen place-items-center bg-canvas px-6">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex items-center gap-3">
            <div class="grid h-9 w-9 place-items-center rounded-lg border-[1.5px] border-navy font-serif text-lg font-bold text-navy">
                {{ $brand->logoInitial() }}
            </div>
            <span class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">
                {{ $brand->institutionName() }}
            </span>
        </div>

        <h1 class="font-serif text-[26px] font-semibold leading-tight">Verifikasi dua langkah</h1>

        <p class="mt-2 text-[13.5px] leading-relaxed text-ink-muted">
            Kata sandi Anda benar. Masukkan enam angka dari aplikasi autentikator untuk
            menyelesaikan proses masuk.
        </p>

        @if (session('galat'))
            <x-alert tone="danger" class="mt-5">{{ session('galat') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('dua-faktor.verifikasi') }}" class="mt-6">
            @csrf

            <label for="kode" class="mb-1.5 block text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                Kode Verifikasi
            </label>

            {{--
                inputmode numeric memunculkan papan angka di ponsel, autocomplete
                one-time-code membuat iOS menawarkan kodenya langsung dari
                notifikasi. Keduanya menghapus salah ketik pada layar yang
                dibuka orang sambil terburu-buru.
            --}}
            <input
                id="kode"
                name="kode"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                required
                maxlength="32"
                class="tabular w-full rounded border border-line bg-surface px-3 py-2.5 text-[18px] tracking-[0.3em]"
                placeholder="000000"
            />

            <p class="mt-2 text-[12px] text-ink-faint">
                Kehilangan ponsel? Masukkan salah satu kode pemulihan Anda di kolom yang sama.
            </p>

            <x-button type="submit" class="mt-5 w-full justify-center py-2.5">Verifikasi</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="text-[12.5px] text-ink-muted underline underline-offset-2">
                Batal, kembali ke halaman masuk
            </button>
        </form>
    </div>
</div>
@endsection
