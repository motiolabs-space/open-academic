@extends('layouts.app')

@section('title', __('tugas_akhir_proposal.judul'))

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
        <x-card :title="__('tugas_akhir_proposal.kartu.riwayat')">
            <p class="mb-4 text-[12.5px] text-ink-muted">
                {{ $ta->mahasiswa->nama }} · <span class="tabular">{{ $ta->mahasiswa->nim }}</span> · {{ $ta->mahasiswa->prodi->nama }}
            </p>
            @include('tugas-akhir._proposal-riwayat', ['daftarProposal' => $ta->proposal])
        </x-card>

        @can('telaah', $proposal)
            <x-card :title="__('tugas_akhir_proposal.kartu.telaah')">
                @include('tugas-akhir._proposal-telaah', ['aksi' => route('dosen.tugas-akhir.proposal.telaah', $proposal)])
            </x-card>
        @endcan
    </div>
@endsection
