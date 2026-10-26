@extends('layouts.pendaftar')

@section('title', __('pmb.akun.judul_daftar'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="font-serif text-[26px] font-semibold">{{ __('pmb.akun.judul_daftar') }}</h1>
        <p class="mb-6 mt-1.5 text-[13px] text-ink-muted">{{ __('pmb.akun.pengantar_daftar') }}</p>

        <x-card>
            <form method="POST" action="{{ route('pmb.daftar.store') }}" class="flex flex-col gap-4">
                @csrf
                <x-field :label="__('pmb.akun.nama')" name="nama" required />
                <x-field :label="__('pmb.akun.email')" name="email" type="email" required />
                <x-field :label="__('pmb.akun.password')" name="password" type="password" required />
                <x-field :label="__('pmb.akun.password_confirmation')" name="password_confirmation" type="password" required />
                <x-button type="submit" class="mt-1 w-full">{{ __('pmb.akun.daftar') }}</x-button>
            </form>
        </x-card>

        <p class="mt-4 text-center text-[13px]">
            <a href="{{ route('pmb.masuk') }}" class="text-navy hover:underline">{{ __('pmb.sudah_punya_akun') }}</a>
        </p>
    </div>
@endsection
