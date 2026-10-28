@extends('layouts.app')

@section('title', __('pengumuman.judul'))

@php use App\Support\Format; @endphp

@section('content')
    @if ($daftar->isEmpty())
        <x-card>
            <x-empty-state :title="__('pengumuman.kosong_judul')" :description="__('pengumuman.kosong_bantuan')" />
        </x-card>
    @else
        <x-card flush>
            @foreach ($daftar as $item)
                <article class="border-b border-line/60 px-5 py-4 last:border-b-0" data-pengumuman="{{ $item->uuid }}">
                    <h2 class="text-[14.5px] font-semibold leading-snug">
                        @if ($item->is_pinned)
                            <x-chip tone="warning" class="mr-1.5">{{ __('pengumuman.disematkan') }}</x-chip>
                        @endif
                        <a href="{{ route($ruteTampil, $item) }}" class="hover:underline">{{ $item->judul }}</a>
                    </h2>
                    <div class="tabular mt-1 text-[11.5px] text-ink-faint">
                        {{ __('pengumuman.terbit', ['tanggal' => Format::tanggal($item->published_at)]) }}
                        @if ($item->berakhir_at)
                            · {{ __('pengumuman.berlaku_sampai', ['tanggal' => Format::tanggal($item->berakhir_at)]) }}
                        @endif
                    </div>
                    @if ($item->ringkasan)
                        <p class="mt-2 text-[13px] leading-relaxed text-ink-muted">{{ $item->ringkasan }}</p>
                    @endif
                    <a href="{{ route($ruteTampil, $item) }}" class="mt-2 inline-block text-[12px] font-semibold text-navy hover:underline">
                        {{ __('pengumuman.baca') }}
                    </a>
                </article>
            @endforeach

            @if ($daftar->hasPages())
                <div class="border-t border-line px-5 py-3">{{ $daftar->links() }}</div>
            @endif
        </x-card>
    @endif
@endsection
