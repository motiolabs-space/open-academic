@extends('layouts.pendaftar')

@section('title', __('pmb.sandi.judul_lupa'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="font-serif text-[26px] font-semibold">{{ __('pmb.sandi.judul_lupa') }}</h1>
        <p class="mb-6 mt-1.5 text-[13px] text-ink-muted">{{ __('pmb.sandi.pengantar_lupa') }}</p>

        <x-card>
            <form method="POST" action="{{ route('pmb.sandi.kirim') }}" class="flex flex-col gap-4">
                @csrf
                <x-field :label="__('pmb.akun.email')" name="email" type="email" required />
                <x-button type="submit" class="mt-1 w-full">{{ __('pmb.sandi.kirim') }}</x-button>
            </form>
        </x-card>

        <p class="mt-4 text-center text-[13px]">
            <a href="{{ route('pmb.masuk') }}" class="text-navy hover:underline">{{ __('pmb.akun.masuk') }}</a>
        </p>
    </div>
@endsection
