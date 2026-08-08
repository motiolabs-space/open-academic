@extends('layouts.app')

@section('title', $judul)

@section('content')
    @if (session('sukses'))
        <div class="mb-5">
            <x-alert tone="success">{{ session('sukses') }}</x-alert>
        </div>
    @endif

    @if ($aplikasi->isEmpty())
        <x-card>
            <div class="px-2 py-10 text-center">
                <div class="text-2xl text-ink-faint" aria-hidden="true">⌘</div>
                <p class="mt-3 text-[14px] font-semibold">Belum ada aplikasi terhubung</p>
                <p class="mx-auto mt-1.5 max-w-md text-[13px] leading-relaxed text-ink-muted">
                    Ketika Anda masuk ke aplikasi kampus lain memakai akun ini, aplikasi
                    tersebut akan tercantum di sini beserta data yang boleh dibacanya.
                </p>
            </div>
        </x-card>
    @else
        <div class="flex flex-col gap-3.5">
            @foreach ($aplikasi as $baris)
                <x-card>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="text-[15px] font-semibold">{{ $baris['client']->name }}</div>

                            <div class="mt-1 text-[12px] text-ink-muted">
                                Diizinkan sejak
                                {{ \Illuminate\Support\Carbon::parse($baris['sejak'])->translatedFormat('j F Y') }}
                            </div>

                            @if ($baris['scopes']->isNotEmpty())
                                <ul class="mt-3 flex flex-col gap-1.5">
                                    @foreach ($baris['scopes'] as $scope)
                                        <li class="flex items-start gap-2 text-[13px] leading-snug text-ink-muted">
                                            <span class="mt-[3px] text-gold" aria-hidden="true">◆</span>
                                            <span>{{ $daftarScope[$scope] ?? $scope }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-3 text-[13px] text-ink-muted">
                                    Hanya memastikan identitas Anda; tidak membaca data lain.
                                </p>
                            @endif
                        </div>

                        <form
                            method="POST"
                            action="{{ route('sso.aplikasi.cabut', $baris['client']->getKey()) }}"
                            class="flex-none"
                            onsubmit="return confirm('Cabut akses {{ $baris['client']->name }}? Aplikasi ini akan meminta izin lagi bila Anda memakainya kembali.');"
                        >
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="outline">Cabut Akses</x-button>
                        </form>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
