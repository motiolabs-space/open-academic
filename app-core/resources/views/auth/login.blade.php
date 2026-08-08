@extends('layouts.base')

@section('title', 'Masuk')

@php
    use App\Services\Branding\BrandingService;
    $brand = app(BrandingService::class);
@endphp

@section('body')
<div class="grid min-h-screen lg:grid-cols-2">
    {{-- Institutional half: navy with the guilloché motif of an official document. --}}
    <div class="guilloche-gold relative hidden flex-col justify-between bg-navy p-12 text-canvas lg:flex">
        <div class="relative flex items-center gap-3">
            <div class="grid h-9 w-9 place-items-center rounded-lg border-[1.5px] border-gold font-serif text-lg font-bold text-gold">
                {{ $brand->logoInitial() }}
            </div>
            <span class="text-[11px] uppercase tracking-[0.14em] text-canvas/75">
                {{ $brand->institutionName() }}
            </span>
        </div>

        <div class="relative max-w-md">
            <h1 class="font-serif text-[40px] font-semibold leading-[1.1]">
                Sistem Akademik Terbuka untuk Perguruan Tinggi Indonesia
            </h1>
            <p class="mt-4 text-[15px] leading-relaxed text-canvas/70">
                Satu sumber kebenaran untuk data akademik: KRS, nilai, transkrip, keuangan,
                dan pelaporan PDDIKTI melalui Neo Feeder.
            </p>
        </div>

        <div class="relative flex gap-2 text-[11px] font-semibold tracking-[0.08em]">
            <span class="rounded-full border border-gold/50 px-3 py-1.5 text-gold">MIT LICENSE</span>
            <span class="rounded-full border border-canvas/25 px-3 py-1.5 text-canvas/80">PDDIKTI READY</span>
        </div>
    </div>

    {{-- Form half --}}
    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden">
                <div class="grid h-9 w-9 place-items-center rounded-lg border-[1.5px] border-gold font-serif text-lg font-bold text-gold">
                    {{ $brand->logoInitial() }}
                </div>
            </div>

            <h2 class="font-serif text-[26px] font-semibold">Masuk ke Portal Akademik</h2>
            <p class="mt-1.5 text-[13px] text-ink-muted">
                Gunakan NIM, NIDN, NIP, atau alamat surel institusi Anda.
            </p>

            @if ($errors->any())
                <div class="mt-5" id="galat-masuk" role="alert">
                    <x-alert tone="danger">{{ $errors->first() }}</x-alert>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-4">
                @csrf

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        NIM / NIDN / NIP / Surel
                    </span>
                    <input
                        type="text"
                        name="identitas"
                        value="{{ old('identitas') }}"
                        required
                        autofocus
                        autocomplete="username"
                        @if ($errors->has('identitas')) aria-invalid="true" aria-describedby="galat-masuk" @endif
                        @class([
                            'w-full rounded-control border bg-surface px-3.5 py-2.5 text-[13.5px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10',
                            'border-danger bg-danger-bg/40' => $errors->has('identitas'),
                            'border-line-input' => ! $errors->has('identitas'),
                        ])
                    >
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        Kata Sandi
                    </span>
                    <input
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-control border border-line-input bg-surface px-3.5 py-2.5 text-[13.5px] outline-none focus:border-navy focus:ring-4 focus:ring-navy/10"
                    >
                </label>

                <label class="flex items-center gap-2 text-[12.5px] text-ink-muted">
                    <input type="checkbox" name="ingat" value="1" class="accent-navy">
                    Ingat perangkat ini
                </label>

                <x-button type="submit" class="mt-1 w-full">Masuk</x-button>
            </form>

            {{-- The "Masuk dengan Akun Kampus (SSO)" button that used to sit here
                 has been removed rather than wired up, because it described the
                 wrong direction.

                 Open Academic *is* the campus account: it runs the OAuth2 server,
                 and other applications redirect their users here. There is no
                 upstream identity provider for this page to delegate to, so the
                 button could never have done anything.

                 Federating upward to an existing campus IdP (Google Workspace,
                 Entra ID, Keycloak) is a separate feature. It needs a policy
                 decision first — an external identity has to be mapped to exactly
                 one of the three identity tables, and getting that wrong lets a
                 lecturer into the student portal. See docs/SSO.md. --}}

            @if (! app()->environment('production'))
                <div class="mt-8 rounded-card border border-line bg-surface px-4 py-3 text-[12px] leading-relaxed text-ink-muted">
                    <div class="mb-1 font-semibold text-ink">Akun demo</div>
                    <div class="tabular">admin@demo.test · dosen1@demo.test · mahasiswa1@demo.test</div>
                    <div class="mt-1">Kata sandi: <span class="font-semibold text-ink">password</span></div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
