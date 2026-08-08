@extends('layouts.base')

@php
    use App\Services\Branding\BrandingService;
    use App\Support\Portal;

    $brand = app(BrandingService::class);
@endphp

@section('title', 'Izinkan Akses')

@section('body')
    <div class="flex min-h-screen items-center justify-center bg-canvas px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center gap-3">
                <div class="grid h-9 w-9 flex-none place-items-center rounded-lg border-[1.5px] border-gold font-serif text-lg font-bold text-gold">
                    {{ $brand->logoInitial() }}
                </div>
                <div class="min-w-0">
                    <div class="truncate text-[13px] font-semibold">{{ $brand->institutionName() }}</div>
                    <div class="text-[11px] text-ink-muted">Persetujuan Akses Aplikasi</div>
                </div>
            </div>

            <div class="rounded-card-lg border border-line bg-surface p-6 shadow-pop">
                <h1 class="font-serif text-[22px] font-semibold leading-snug">
                    Izinkan <span class="text-navy">{{ $client->name }}</span> mengakses akun Anda?
                </h1>

                <div class="mt-4 flex items-center gap-3 rounded-card border border-line bg-canvas px-4 py-3">
                    <span class="grid h-9 w-9 flex-none place-items-center rounded-full bg-navy font-serif text-[13px] font-semibold text-gold">
                        {{ Portal::inisial() }}
                    </span>
                    <div class="min-w-0 leading-tight">
                        <div class="truncate text-[13px] font-semibold">{{ $user->nama }}</div>
                        <div class="text-[11.5px] text-ink-muted">{{ Portal::konteks() }}</div>
                    </div>
                </div>

                @if (count($scopes) > 0)
                    <p class="mt-5 text-[12.5px] font-semibold uppercase tracking-[0.08em] text-ink-muted">
                        Aplikasi ini akan dapat
                    </p>

                    <ul class="mt-2.5 flex flex-col gap-2">
                        @foreach ($scopes as $scope)
                            <li class="flex items-start gap-2.5 text-[13.5px] leading-snug">
                                <span class="mt-[3px] text-gold" aria-hidden="true">◆</span>
                                <span>{{ $scope->description }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-5 text-[13.5px] leading-relaxed text-ink-muted">
                        Aplikasi ini tidak meminta akses ke data apa pun selain memastikan
                        Anda benar-benar pemilik akun ini.
                    </p>
                @endif

                {{-- Said plainly. Someone approving this is entitled to know it is not
                     permanent and where to undo it. --}}
                <p class="mt-5 rounded-card border border-line bg-canvas px-4 py-3 text-[12px] leading-relaxed text-ink-muted">
                    Anda dapat mencabut izin ini kapan saja melalui menu
                    <span class="font-semibold text-ink">Profil → Aplikasi Terhubung</span>.
                    Kata sandi Anda tidak pernah dibagikan kepada aplikasi ini.
                </p>

                <div class="mt-6 flex flex-col gap-2.5 sm:flex-row-reverse">
                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="sm:flex-1">
                        @csrf
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <x-button type="submit" class="w-full">Izinkan</x-button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="sm:flex-1">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <x-button type="submit" variant="outline" class="w-full">Tolak</x-button>
                    </form>
                </div>
            </div>

            <p class="mt-5 text-center text-[11.5px] text-ink-faint">
                Bukan Anda?
                <a href="{{ route('logout') }}"
                   class="font-semibold text-navy underline"
                   onclick="event.preventDefault(); document.getElementById('keluar-sso').submit();">
                    Keluar
                </a>
            </p>

            <form id="keluar-sso" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
        </div>
    </div>
@endsection
