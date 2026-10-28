@extends('layouts.app')

@section('title', $pengumuman->judul)

@php use App\Support\Format; @endphp

@section('content')
    <x-card class="max-w-3xl">
        <div class="tabular mb-4 text-[11.5px] text-ink-faint">
            {{ __('pengumuman.terbit', ['tanggal' => Format::tanggal($pengumuman->published_at)]) }}
            @if ($pengumuman->berakhir_at)
                · {{ __('pengumuman.berlaku_sampai', ['tanggal' => Format::tanggal($pengumuman->berakhir_at)]) }}
            @endif
        </div>

        @if ($pengumuman->ringkasan)
            <p class="mb-4 text-[14px] font-medium leading-relaxed">{{ $pengumuman->ringkasan }}</p>
        @endif

        {{-- Plain text with its line breaks kept; never rendered as HTML. --}}
        <div class="whitespace-pre-line text-[13.5px] leading-relaxed">{{ $pengumuman->isi }}</div>

        <div class="mt-6 border-t border-line pt-4">
            <x-button variant="ghost" :href="route($ruteDaftar)">{{ __('pengumuman.kembali') }}</x-button>
        </div>
    </x-card>
@endsection
