@extends('layouts.app')

@section('title', __('angka_kredit.judul_dosen'))

@section('content')
    <div class="mb-5">
        <x-alert tone="info">{{ __('angka_kredit.pengantar_dosen') }}</x-alert>
    </div>

    <div class="space-y-5">
        @include('angka-kredit.rekap')
        @include('angka-kredit.buku', ['admin' => false])
    </div>
@endsection
