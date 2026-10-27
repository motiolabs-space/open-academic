@extends('layouts.app')

@section('title', $judul)

@section('content')
    @if (session('galat'))
        <div class="mb-5"><x-alert tone="danger">{{ session('galat') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <form method="POST" action="{{ $kegiatan ? route('admin.kegiatan-lapangan.update', $kegiatan) : route('admin.kegiatan-lapangan.store') }}" class="space-y-5">
        @csrf
        @if ($kegiatan)
            @method('PUT')
        @endif

        <x-card :title="__('kegiatan_lapangan.kartu.identitas')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field :label="__('kegiatan_lapangan.field.nama')" name="nama" :value="$kegiatan?->nama" required class="sm:col-span-2" />
                <x-field :label="__('kegiatan_lapangan.field.jenis')" name="jenis" :options="$jenisPilihan" :value="$kegiatan?->jenis->value" required />
                <x-field :label="__('kegiatan_lapangan.field.tahun_akademik_id')" name="tahun_akademik_id" :options="$daftarTerm" :value="$kegiatan?->tahun_akademik_id ?? $termAktif" required />
                <x-field :label="__('kegiatan_lapangan.field.tanggal_mulai')" name="tanggal_mulai" type="date" :value="$kegiatan?->tanggal_mulai?->toDateString()" required />
                <x-field :label="__('kegiatan_lapangan.field.tanggal_selesai')" name="tanggal_selesai" type="date" :value="$kegiatan?->tanggal_selesai?->toDateString()" required />
                <x-field :label="__('kegiatan_lapangan.field.nomor_sk')" name="nomor_sk" :value="$kegiatan?->nomor_sk" :hint="__('kegiatan_lapangan.petunjuk.sk')" />
                <x-field :label="__('kegiatan_lapangan.field.tanggal_sk')" name="tanggal_sk" type="date" :value="$kegiatan?->tanggal_sk?->toDateString()" />
                <x-field :label="__('kegiatan_lapangan.field.keterangan')" name="keterangan" type="textarea" :value="$kegiatan?->keterangan" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card :title="__('kegiatan_lapangan.kartu.aturan')" :meta="__('kegiatan_lapangan.petunjuk.aturan')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field :label="__('kegiatan_lapangan.field.min_sks')" name="min_sks" type="number" :value="$kegiatan?->min_sks ?? $bawaan['min_sks']" required />
                <x-field :label="__('kegiatan_lapangan.field.ukuran_kelompok')" name="ukuran_kelompok" type="number" :value="$kegiatan?->ukuran_kelompok ?? $bawaan['ukuran_kelompok']" required />
                <x-field :label="__('kegiatan_lapangan.field.bobot_mitra')" name="bobot_mitra" type="number" :value="$kegiatan?->bobot_mitra ?? $bawaan['bobot_mitra']" required />
                <x-field :label="__('kegiatan_lapangan.field.campur_prodi')" name="campur_prodi" type="checkbox" :value="$kegiatan?->campur_prodi ?? $bawaan['campur_prodi']" :hint="__('kegiatan_lapangan.petunjuk.campur_prodi')" />
                <x-field :label="__('kegiatan_lapangan.field.campur_jenis_kelamin')" name="campur_jenis_kelamin" type="checkbox" :value="$kegiatan?->campur_jenis_kelamin ?? $bawaan['campur_jenis_kelamin']" :hint="__('kegiatan_lapangan.petunjuk.campur_jenis_kelamin')" />
                <x-field :label="__('kegiatan_lapangan.field.pendaftaran_dibuka')" name="pendaftaran_dibuka" type="checkbox" :value="$kegiatan?->pendaftaran_dibuka ?? false" :hint="__('kegiatan_lapangan.petunjuk.pendaftaran')" />
            </div>
        </x-card>

        <div class="flex gap-2">
            <x-button type="submit">{{ __('kegiatan_lapangan.aksi.simpan') }}</x-button>
            <x-button variant="outline" href="{{ $kegiatan ? route('admin.kegiatan-lapangan.show', $kegiatan) : route('admin.kegiatan-lapangan.index') }}">{{ __('kegiatan_lapangan.aksi.batal') }}</x-button>
        </div>
    </form>
@endsection
