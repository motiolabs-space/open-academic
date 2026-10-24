@extends('layouts.base')

@section('title', __('helpdesk.faq.judul'))

{{-- Halaman publik tanpa data pribadi: boleh diindeks. --}}
@section('robots', 'index, follow')
@section('deskripsi', __('helpdesk.faq.pengantar'))

@php
    use App\Services\Branding\BrandingService;

    $brand = app(BrandingService::class);
@endphp

@section('body')
    <div class="min-h-screen bg-canvas">
        <header class="border-b border-line bg-navy px-6 py-5 text-canvas">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-lg border-[1.5px] border-gold font-serif text-lg font-bold text-gold">
                        {{ $brand->logoInitial() }}
                    </div>
                    <div>
                        <div class="text-[13px] font-semibold">{{ $brand->institutionName() }}</div>
                        <div class="text-[11px] uppercase tracking-[0.14em] text-canvas/70">{{ __('helpdesk.faq.judul') }}</div>
                    </div>
                </div>
                <x-button variant="gold" size="sm" :href="route('login')">{{ __('helpdesk.faq.masuk') }}</x-button>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-8">
            <h1 class="font-serif text-[28px] font-semibold leading-tight">{{ __('helpdesk.faq.subjudul') }}</h1>
            <p class="mt-2 max-w-2xl text-[13.5px] leading-relaxed text-ink-muted">{{ __('helpdesk.faq.pengantar') }}</p>

            <div class="mt-6 space-y-5">
                @forelse ($kelompok as $nama => $daftar)
                    <x-card :title="$nama !== '' ? $nama : __('helpdesk.faq.umum')" flush>
                        @foreach ($daftar as $f)
                            <details class="group border-b border-line/50 px-5 py-3 last:border-b-0">
                                <summary class="cursor-pointer text-[13.5px] font-medium hover:text-gold">{{ $f->pertanyaan }}</summary>
                                <div class="mt-2 whitespace-pre-line text-[13px] leading-relaxed text-ink-muted">{{ $f->jawaban }}</div>
                            </details>
                        @endforeach
                    </x-card>
                @empty
                    <x-empty-state :title="__('helpdesk.faq.kosong')" />
                @endforelse
            </div>

            <x-card class="mt-6">
                <p class="text-[13px] leading-relaxed text-ink-muted">{{ __('helpdesk.faq.ajukan') }}</p>
                <div class="mt-3">
                    <x-button :href="route('login')" size="sm">{{ __('helpdesk.faq.masuk') }}</x-button>
                </div>
            </x-card>
        </main>
    </div>
@endsection
