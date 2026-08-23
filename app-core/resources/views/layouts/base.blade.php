@php use App\Services\Branding\BrandingService; $brand = app(BrandingService::class); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name')) · {{ $brand->institutionShortName() }}</title>

    {{--
        Mesin pencari ditolak secara BAWAAN, dan halaman publik yang meminta
        sebaliknya.

        Terbalik dari kebiasaan, dan disengaja. Halaman verifikasi surat memuat
        nama, NIM, dan program studi seseorang — seluruh pengamanannya bertumpu
        pada UUID yang tak dapat ditebak, dan pengindeksan meniadakan itu
        sepenuhnya: URL-nya tidak lagi perlu ditebak karena sudah ada di indeks,
        dan nama mahasiswa menjadi hasil pencarian yang menetap.

        Bawaan "index" berarti setiap layar baru harus diingat untuk ditutup.
        Bawaan "noindex" berarti hanya yang memang publik yang perlu diingat.
    --}}
    <meta name="robots" content="@yield('robots', 'noindex, nofollow')">

    <meta name="description" content="@yield('deskripsi', 'Sistem informasi akademik ' . $brand->institutionName() . '.')">
    <link rel="canonical" href="{{ url()->current() }}">

    {{--
        Lewat asset(), bukan mengandalkan peramban menjenguk /favicon.ico.

        Jenguk bawaan itu selalu menuju AKAR DOMAIN, sehingga pemasangan di
        subfolder — pola yang justru dipakai repo ini — tidak akan pernah
        terlayani. Persis kelas kekeliruan yang sama dengan URL aset Vite.
    --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <meta name="theme-color" content="{{ $brand->primaryColor() }}">

    {{--
        Pratinjau tautan — di Indonesia hampir selalu WhatsApp.

        Keterangannya sengaja umum dan tidak pernah memuat data halaman: tautan
        verifikasi yang diteruskan ke sebuah grup tidak boleh ikut membawa nama
        dan NIM seseorang ke dalam pratinjaunya.
    --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $brand->institutionName() }}">
    <meta property="og:title" content="@yield('title', config('app.name'))">
    <meta property="og:description" content="@yield('deskripsi', 'Sistem informasi akademik ' . $brand->institutionName() . '.')">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($brand->logoUrl())
        <meta property="og:image" content="{{ $brand->logoUrl() }}">
    @endif
    <meta name="twitter:card" content="summary">

    {{-- Font di-host sendiri lewat @fontsource, diimpor di resources/css/app.css
         dan ikut terbundel Vite. Tidak ada lagi permintaan ke fonts.googleapis.com
         maupun fonts.gstatic.com — alasannya ada di komentar app.css. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Tenant colour overrides reach the stylesheet without recompiling Tailwind. --}}
    <style>:root { {!! $brand->cssVariables() !!} }</style>
</head>
<body class="min-h-screen antialiased">
    @yield('body')

    <x-toast-stack />
</body>
</html>
