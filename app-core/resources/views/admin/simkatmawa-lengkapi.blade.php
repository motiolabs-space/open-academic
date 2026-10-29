@extends('layouts.app')

@section('title', __('simkatmawa.lengkapi.judul'))

@section('content')
    @if (session('sukses'))
        <div class="mb-5"><x-alert tone="success">{{ session('sukses') }}</x-alert></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
        <x-card :title="$poin->judul" :meta="$poin->mahasiswa->nim.' · '.$poin->mahasiswa->nama">
            <p class="mb-4 text-[12.5px] leading-relaxed text-ink-muted">{{ __('simkatmawa.lengkapi.pengantar') }}</p>

            <form method="POST" action="{{ route('admin.poin-kemahasiswaan.simkatmawa.simpan', $poin) }}"
                class="grid gap-3 sm:grid-cols-2">
                @csrf
                @method('PUT')

                <x-field :label="__('simkatmawa.isian.tingkat')" name="tingkat" :options="$opsi['tingkat']"
                    :value="$poin->tingkat?->value" :hint="__('simkatmawa.petunjuk.tingkat')" />
                <x-field :label="__('simkatmawa.isian.penyelenggara')" name="penyelenggara" :value="$poin->penyelenggara" />

                <x-field :label="__('simkatmawa.isian.peringkat')" name="peringkat" :options="$opsi['peringkat']" :value="$poin->peringkat?->value" />
                <x-field :label="__('simkatmawa.isian.bidang_lomba')" name="bidang_lomba" :options="$opsi['bidang_lomba']" :value="$poin->bidang_lomba?->value" />
                <x-field :label="__('simkatmawa.isian.bentuk_kepesertaan')" name="bentuk_kepesertaan" :options="$opsi['bentuk_kepesertaan']" :value="$poin->bentuk_kepesertaan?->value" />
                <x-field :label="__('simkatmawa.isian.moda_pelaksanaan')" name="moda_pelaksanaan" :options="$opsi['moda_pelaksanaan']" :value="$poin->moda_pelaksanaan?->value" />

                <x-field :label="__('simkatmawa.isian.jumlah_pt_peserta')" name="jumlah_pt_peserta" type="number"
                    :value="$poin->jumlah_pt_peserta" :hint="__('simkatmawa.petunjuk.jumlah_pt_peserta')" />
                <x-field :label="__('simkatmawa.isian.jumlah_negara_peserta')" name="jumlah_negara_peserta" type="number"
                    :value="$poin->jumlah_negara_peserta" :hint="__('simkatmawa.petunjuk.jumlah_negara_peserta')" />

                <x-field :label="__('simkatmawa.isian.jenis_rekognisi')" name="jenis_rekognisi" :options="$opsi['jenis_rekognisi']" :value="$poin->jenis_rekognisi?->value" />
                <x-field :label="__('simkatmawa.isian.nomor_sertifikat')" name="nomor_sertifikat" :value="$poin->nomor_sertifikat" />

                <x-field :label="__('simkatmawa.isian.tanggal_selesai')" name="tanggal_selesai" type="date" :value="$poin->tanggal_selesai" />
                <x-field :label="__('simkatmawa.isian.dosen_pembimbing')" name="dosen_pembimbing" :options="$dosen" :value="$poin->dosenPembimbing?->uuid" />

                <x-field class="sm:col-span-2" :label="__('simkatmawa.isian.tautan_sertifikat')" name="tautan_sertifikat" type="url"
                    :value="$poin->tautan_sertifikat" :hint="__('simkatmawa.petunjuk.tautan')" />
                <x-field class="sm:col-span-2" :label="__('simkatmawa.isian.tautan_bukti')" name="tautan_bukti" type="url" :value="$poin->tautan_bukti" />
                <x-field class="sm:col-span-2" :label="__('simkatmawa.isian.tautan_surat_tugas')" name="tautan_surat_tugas" type="url" :value="$poin->tautan_surat_tugas" />

                <div class="flex flex-wrap gap-2 sm:col-span-2">
                    <x-button type="submit">{{ __('simkatmawa.lengkapi.simpan') }}</x-button>
                    <x-button variant="outline" :href="route('admin.simkatmawa')">{{ __('simkatmawa.lengkapi.kembali') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('simkatmawa.lengkapi.temuan')">
            <div class="mb-3 text-[12px] text-ink-muted">
                {{ __('simkatmawa.lengkapi.kategori_katalog') }}:
                <span class="font-semibold text-ink">{{ $poin->kategori->simkatmawa_kategori?->label() ?? '—' }}</span>
            </div>

            @if ($temuan === null)
                <x-alert tone="warning">{{ __('simkatmawa.lengkapi.tidak_dilaporkan') }}</x-alert>
            @elseif ($temuan->galat === [] && $temuan->peringatan === [])
                <x-alert tone="success">{{ __('simkatmawa.lengkapi.lengkap') }}</x-alert>
            @else
                <ul class="space-y-1.5 text-[12.5px]">
                    @foreach ($temuan->galat as $g)
                        <li class="text-danger">{{ $g }}</li>
                    @endforeach
                    @foreach ($temuan->peringatan as $w)
                        <li class="text-warning">{{ $w }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
