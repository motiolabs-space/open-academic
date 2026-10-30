@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('akreditasi.judul'))

@section('content')
    @include('admin.master.partials.tabs')

    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-5"><x-alert tone="danger">{{ $errors->first() }}</x-alert></div>
    @endif

    <div class="mb-5">
        <x-alert tone="info">{{ __('akreditasi.pengantar') }}</x-alert>
    </div>

    @if ($prodi->riwayatAkreditasi->isEmpty() && filled($prodi->akreditasi))
        <div class="mb-5">
            <x-alert tone="warning">{{ __('akreditasi.lama_tanpa_riwayat', ['peringkat' => $prodi->akreditasi]) }}</x-alert>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <x-card flush :title="$prodi->namaLengkap()" :meta="__('akreditasi.riwayat')">
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3 text-[12.5px] text-ink-muted">
                <span>{{ __('akreditasi.field.status') }}:</span>
                <x-chip :tone="$keadaan->tone()" dot>{{ $keadaan->label() }}</x-chip>
                @if ($berlaku)
                    <span class="tabular">{{ __('akreditasi.peringatan.sampai', ['tanggal' => Format::tanggal($berlaku->tanggal_kedaluwarsa)]) }}</span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('akreditasi.field.peringkat') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('akreditasi.field.nomor_sk') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('akreditasi.field.masa_berlaku') }}</th>
                            <th class="px-5 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prodi->riwayatAkreditasi as $a)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-semibold">{{ $a->peringkat->label() }}</div>
                                    <div class="text-[11.5px] text-ink-faint">{{ $a->lembaga->label() }}</div>
                                    @if ($berlaku?->is($a))
                                        <x-chip tone="success" class="mt-1">{{ __('akreditasi.keadaan.berlaku') }}</x-chip>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="tabular">{{ $a->nomor_sk }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">
                                        {{ Format::tanggal($a->tanggal_sk) }}
                                        @if ($a->pencatat) · {{ __('akreditasi.dicatat_oleh', ['nama' => $a->pencatat->nama]) }} @endif
                                    </div>
                                    @if ($a->dokumen_path)
                                        <a href="{{ route('berkas.akreditasi-prodi', $a) }}" class="text-[11.5px] text-navy hover:underline">{{ __('akreditasi.unduh_sk') }}</a>
                                    @endif
                                    @if ($a->keterangan)
                                        <div class="mt-1 text-[11.5px] text-ink-muted">{{ $a->keterangan }}</div>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3">
                                    {{ Format::rentangTanggal($a->tanggal_berlaku, $a->tanggal_kedaluwarsa) }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @can('delete', $a)
                                        <form method="POST" action="{{ route('admin.master.prodi.akreditasi.destroy', [$prodi, $a]) }}"
                                            data-confirm="{{ __('akreditasi.tarik_konfirmasi', ['nomor' => $a->nomor_sk]) }}">
                                            @csrf @method('DELETE')
                                            <x-button type="submit" variant="ghost" size="sm">{{ __('akreditasi.tarik') }}</x-button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12">
                                    <x-empty-state :title="__('akreditasi.kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        @can('create', App\Models\Akademik\AkreditasiProdi::class)
            <x-card :title="__('akreditasi.catat')">
                <form method="POST" action="{{ route('admin.master.prodi.akreditasi.store', $prodi) }}"
                    enctype="multipart/form-data" class="flex flex-col gap-3.5">
                    @csrf
                    <x-field :label="__('akreditasi.field.lembaga')" name="lembaga" :options="$lembagaPilihan" required />
                    <x-field :label="__('akreditasi.field.peringkat')" name="peringkat" :options="$peringkatPilihan" required />
                    <x-field :label="__('akreditasi.field.nomor_sk')" name="nomor_sk" required />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field :label="__('akreditasi.field.tanggal_sk')" name="tanggal_sk" type="date" required />
                        <x-field :label="__('akreditasi.field.tanggal_berlaku')" name="tanggal_berlaku" type="date"
                            :hint="__('akreditasi.field.tanggal_berlaku_hint')" />
                    </div>
                    <x-field :label="__('akreditasi.field.tanggal_kedaluwarsa')" name="tanggal_kedaluwarsa" type="date" required />
                    <x-field :label="__('akreditasi.field.dokumen')" name="dokumen" type="file" />
                    <x-field :label="__('akreditasi.field.keterangan')" name="keterangan" type="textarea" />
                    <x-button type="submit" class="mt-1 w-full">{{ __('akreditasi.simpan') }}</x-button>
                </form>
            </x-card>
        @endcan
    </div>
@endsection
