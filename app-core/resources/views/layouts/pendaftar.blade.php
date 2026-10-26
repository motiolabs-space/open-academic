{{--
    Kerangka portal calon mahasiswa (guard `pendaftar`).

    Tanpa sidebar portal kampus: calon belum anggota kampus, dan menu yang
    berisi KRS dan nilai hanya membingungkan orang yang baru memilih prodi.
    Satu kolom, lebar baca, tombol keluar yang jelas.
--}}
@extends('layouts.base')

@php
    use App\Services\Branding\BrandingService;
    $brand = app(BrandingService::class);
    $akun = auth('pendaftar')->user();
@endphp

@section('body')
    <a
        href="#konten"
        class="fixed left-4 top-4 z-50 -translate-y-24 whitespace-nowrap rounded-control bg-navy px-4 py-2.5 text-[13px] font-semibold text-canvas transition-transform focus:translate-y-0"
    >
        {{ __('pmb.lompat') }}
    </a>

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex w-full max-w-[1040px] items-center justify-between gap-4 px-4 py-3.5 sm:px-7">
            <a href="{{ $akun ? route('pmb.portal.index') : route('pmb.beranda') }}" class="flex min-w-0 items-center gap-3">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border-[1.5px] border-gold font-serif text-base font-bold text-gold">
                    {{ $brand->logoInitial() }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-[13px] font-semibold text-ink">{{ $brand->institutionName() }}</span>
                    <span class="block text-[11px] uppercase tracking-[0.1em] text-ink-muted">{{ __('pmb.portal') }}</span>
                </span>
            </a>

            @if ($akun)
                <div class="flex items-center gap-3">
                    <span class="hidden text-[12.5px] text-ink-muted sm:inline">{{ $akun->nama }}</span>
                    <form method="POST" action="{{ route('pmb.keluar') }}">
                        @csrf
                        <x-button type="submit" variant="outline" size="sm">{{ __('pmb.akun.keluar') }}</x-button>
                    </form>
                </div>
            @else
                <x-button :href="route('pmb.masuk')" variant="outline" size="sm">{{ __('pmb.akun.masuk') }}</x-button>
            @endif
        </div>
    </header>

    <main id="konten" tabindex="-1" class="mx-auto w-full max-w-[1040px] px-4 pb-16 pt-6 sm:px-7">
        @hasSection('judul_halaman')
            <h1 class="mb-1 font-serif text-[26px] font-semibold">@yield('judul_halaman')</h1>
        @endif
        @hasSection('subjudul_halaman')
            <p class="mb-6 text-[13px] text-ink-muted">@yield('subjudul_halaman')</p>
        @endif

        @yield('content')
    </main>
@endsection
