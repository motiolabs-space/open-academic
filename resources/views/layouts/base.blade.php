@php use App\Services\Branding\BrandingService; $brand = app(BrandingService::class); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name')) · {{ $brand->institutionShortName() }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Tenant colour overrides reach the stylesheet without recompiling Tailwind. --}}
    <style>:root { {!! $brand->cssVariables() !!} }</style>
</head>
<body class="min-h-screen antialiased">
    @yield('body')

    <x-toast-stack />
</body>
</html>
