@extends('layouts.app')

@section('title', __('kepegawaian.judul'))

@section('content')
    @include('kepegawaian.pesan')

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="text-[13px] text-ink-muted">
            {{ collect([$dosen->nidn ? 'NIDN '.$dosen->nidn : null, $dosen->prodi?->nama, $dosen->unitKerja?->nama])
                ->filter()->implode(' · ') }}
            @unless ($dosen->is_active)
                · <x-chip tone="danger">{{ __('kepegawaian.nonaktif') }}</x-chip>
            @endunless
        </div>
        <x-button size="sm" variant="outline" :href="route('admin.kepegawaian.antrean')">
            {{ __('kepegawaian.aksi.antrean') }}
        </x-button>
    </div>

    <div class="mb-5">
        <x-alert tone="info">{{ __('kepegawaian.pengantar_admin') }}</x-alert>
    </div>

    @include('kepegawaian.riwayat', ['portal' => 'admin'])
@endsection
