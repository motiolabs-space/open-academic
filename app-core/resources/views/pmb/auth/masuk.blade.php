@extends('layouts.pendaftar')

@section('title', __('pmb.akun.judul_masuk'))

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="font-serif text-[26px] font-semibold">{{ __('pmb.akun.judul_masuk') }}</h1>
        <p class="mb-6 mt-1.5 text-[13px] text-ink-muted">{{ __('pmb.akun.pengantar_masuk') }}</p>

        <x-card>
            <form method="POST" action="{{ route('pmb.masuk.store') }}" class="flex flex-col gap-4">
                @csrf
                <x-field :label="__('pmb.akun.email')" name="email" type="email" required />
                <x-field :label="__('pmb.akun.password')" name="password" type="password" required />
                <label class="flex items-center gap-2 text-[12.5px] text-ink-muted">
                    <input type="checkbox" name="ingat" value="1" class="accent-navy">
                    {{ __('pmb.akun.ingat') }}
                </label>
                <x-button type="submit" class="mt-1 w-full">{{ __('pmb.akun.masuk') }}</x-button>
            </form>
        </x-card>

        <div class="mt-4 flex flex-col items-center gap-2 text-[13px]">
            <a href="{{ route('pmb.sandi.lupa') }}" class="text-navy hover:underline">{{ __('pmb.akun.lupa') }}</a>
            <a href="{{ route('pmb.daftar') }}" class="text-navy hover:underline">{{ __('pmb.akun.belum_punya') }}</a>
            <a href="{{ route('login') }}" class="text-ink-muted hover:underline">{{ __('pmb.akun.portal_mahasiswa') }}</a>
        </div>

        @if (! app()->environment('production'))
            <div class="mt-8 rounded-card border border-line bg-surface px-4 py-3 text-[12px] leading-relaxed text-ink-muted">
                <div class="mb-1 font-semibold text-ink">{{ __('pmb.akun.demo') }}</div>
                <div class="tabular">calon.draf@demo.test · calon.bayar@demo.test · calon.seleksi@demo.test · calon.lulus@demo.test</div>
                <div class="mt-1">{{ __('pmb.akun.password') }}: <span class="font-semibold text-ink">password</span></div>
            </div>
        @endif
    </div>
@endsection
