@php use App\Services\Branding\BrandingService; $brand = app(BrandingService::class); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name')) · {{ $brand->institutionShortName() }}</title>

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
