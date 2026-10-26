@extends('layouts.app')

@section('title', __('kepegawaian.judul'))

@section('content')
    @include('kepegawaian.pesan')

    <div class="mb-5">
        <x-alert tone="info">{{ __('kepegawaian.pengantar') }}</x-alert>
    </div>

    @include('kepegawaian.riwayat', ['portal' => 'dosen'])
@endsection
