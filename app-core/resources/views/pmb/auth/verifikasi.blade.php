@extends('layouts.pendaftar')

@section('title', __('pmb.verifikasi.judul'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-6 font-serif text-[26px] font-semibold">{{ __('pmb.verifikasi.judul') }}</h1>

        <x-card>
            <p class="text-[13.5px] leading-relaxed">{{ __('pmb.verifikasi.pesan', ['email' => $akun->email]) }}</p>

            <form method="POST" action="{{ route('pmb.verifikasi.kirim') }}" class="mt-4">
                @csrf
                <x-button type="submit" variant="outline">{{ __('pmb.verifikasi.kirim_ulang') }}</x-button>
            </form>
        </x-card>
    </div>
@endsection
