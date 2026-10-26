@extends('layouts.pendaftar')

@section('title', __('pmb.sandi.judul_atur_ulang'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="mb-6 font-serif text-[26px] font-semibold">{{ __('pmb.sandi.judul_atur_ulang') }}</h1>

        <x-card>
            <form method="POST" action="{{ route('pmb.sandi.simpan') }}" class="flex flex-col gap-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-field :label="__('pmb.akun.email')" name="email" type="email" required />
                <x-field :label="__('pmb.akun.password')" name="password" type="password" required />
                <x-field :label="__('pmb.akun.password_confirmation')" name="password_confirmation" type="password" required />
                <x-button type="submit" class="mt-1 w-full">{{ __('pmb.sandi.simpan') }}</x-button>
            </form>
        </x-card>
    </div>
@endsection
