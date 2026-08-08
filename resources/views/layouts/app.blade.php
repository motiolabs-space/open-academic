@extends('layouts.base')

@section('body')
    {{-- Without this, reaching the page content by keyboard means tabbing past
         every sidebar link first — fourteen of them on the admin portal, on
         every single page load. --}}
    {{-- Parked off-screen with a transform rather than `sr-only`, because
         `focus:not-sr-only` loses to `sr-only`'s own width/height and the link
         stays a 1px dot even while focused. --}}
    <a
        href="#konten"
        class="fixed left-4 top-4 z-50 -translate-y-24 whitespace-nowrap rounded-control bg-navy px-4 py-2.5 text-[13px] font-semibold text-canvas transition-transform focus:translate-y-0"
    >
        Lompat ke konten utama
    </a>

    <div class="flex min-h-screen">
        @include('partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('partials.topbar')

            {{-- Extra bottom padding on mobile so the fixed bottom nav never
                 covers the last row of a table. --}}
            <main id="konten" tabindex="-1" class="mx-auto w-full max-w-[1240px] px-4 pb-28 pt-6 sm:px-7 md:pb-16">
                <x-page-header
                    :title="$judul ?? config('app.name')"
                    :context="$konteks ?? null"
                    :breadcrumb="$breadcrumb ?? []"
                >@yield('aksi')</x-page-header>

                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.bottom-nav')
@endsection
