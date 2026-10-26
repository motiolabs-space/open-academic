@extends('layouts.app')

@section('title', $judul)

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    <form method="POST"
        action="{{ $peristiwa ? route('admin.kalender.peristiwa.update', $peristiwa) : route('admin.kalender.peristiwa.store') }}">
        @csrf
        @if ($peristiwa)
            @method('PUT')
        @endif

        <x-card class="mb-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field class="sm:col-span-2" :label="__('kalender.field.judul')" name="judul" :value="$peristiwa?->judul" required />
                <x-field :label="__('kalender.field.jenis')" name="jenis" :options="$jenisPilihan" :value="$peristiwa?->jenis?->value ?? 'libur'" required
                    :hint="__('kalender.petunjuk.libur')" />
                <x-field :label="__('kalender.field.status')" name="status" :options="$statusPilihan" :value="$peristiwa?->status?->value ?? 'draf'" required
                    :hint="__('kalender.petunjuk.status')" />
                <x-field :label="__('kalender.field.tanggal_mulai')" name="tanggal_mulai" type="date"
                    :value="$peristiwa?->tanggal_mulai?->toDateString() ?? $tanggalAwal" required />
                <x-field :label="__('kalender.field.tanggal_selesai')" name="tanggal_selesai" type="date"
                    :value="$peristiwa?->tanggal_selesai?->toDateString()" :hint="__('kalender.petunjuk.tanggal_selesai')" />
                <x-field :label="__('kalender.field.sasaran')" name="sasaran" :options="$sasaranPilihan" :value="$peristiwa?->sasaran?->value ?? 'semua'" required
                    :hint="__('kalender.petunjuk.sasaran')" />
                <x-field :label="__('kalender.field.prodi')" name="prodi_id" :options="$prodiPilihan" :value="$peristiwa?->prodi_id"
                    :hint="__('kalender.petunjuk.prodi')" />
                <x-field class="sm:col-span-2" :label="__('kalender.field.keterangan')" name="keterangan" type="textarea" :value="$peristiwa?->keterangan" />
            </div>
        </x-card>

        <div class="flex flex-wrap justify-end gap-2">
            <x-button href="{{ route('admin.kalender.peristiwa.index') }}" variant="ghost">{{ __('kalender.aksi.batal') }}</x-button>
            <x-button type="submit">{{ __('kalender.aksi.simpan') }}</x-button>
        </div>
    </form>
@endsection
