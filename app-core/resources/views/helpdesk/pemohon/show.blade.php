@php use App\Enums\StatusTiket; @endphp
@extends('layouts.app')

@section('title', $tiket->nomor)

@section('aksi')
    <x-button href="{{ route($prefix.'.helpdesk') }}" variant="outline" size="sm">← {{ __('helpdesk.daftar.judul') }}</x-button>
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-5">
            @include('helpdesk.partials.percakapan', ['tiket' => $tiket, 'percakapan' => $percakapan])

            @if ($tiket->status === StatusTiket::Ditutup)
                <x-alert tone="info">{{ __('helpdesk.tiket.ditutup_info') }}</x-alert>
            @elseif ($bolehBalas)
                @if ($tiket->status === StatusTiket::Selesai)
                    <x-alert tone="success">{{ __('helpdesk.tiket.selesai_info') }}</x-alert>
                @endif

                <x-card :title="__('helpdesk.form.balas_judul')">
                    <form method="POST" action="{{ route($prefix.'.helpdesk.balas', $tiket) }}"
                        enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <x-field :label="__('helpdesk.form.balas')" name="isi" type="textarea" required
                            :placeholder="__('helpdesk.form.balasan_placeholder')" />
                        <x-field :label="__('helpdesk.form.lampiran')" name="lampiran" type="file"
                            :hint="__('helpdesk.form.lampiran_hint', ['kb' => config('berkas.maks_kb')])" />
                        <x-button type="submit">{{ __('helpdesk.form.kirim_balasan') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            <x-card :title="$tiket->nomor">
                @include('helpdesk.partials.ringkasan', ['tiket' => $tiket])
            </x-card>
        </div>
    </div>
@endsection
