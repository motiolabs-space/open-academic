@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('kalender.judul_kelola'))

@section('aksi')
    <x-button href="{{ route('admin.kalender.peristiwa.create') }}" size="sm">+ {{ __('kalender.aksi.baru') }}</x-button>
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'peringatan' => 'warning', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-[repeat(3,minmax(0,1fr))_auto]">
            <x-field :label="__('kalender.field.tahun')" name="tahun" :options="$tahunPilihan" :value="$tahun" required />
            <x-field :label="__('kalender.field.jenis')" name="jenis" :options="$jenisPilihan" :value="$jenis?->value" />
            <x-field :label="__('kalender.field.status')" name="status" :options="$statusPilihan" :value="$status?->value" />
            <div class="flex items-end"><x-button type="submit">{{ __('kalender.aksi.tampilkan') }}</x-button></div>
        </form>
    </x-card>

    <x-card flush :title="__('kalender.judul_kelola')" :meta="__('kalender.petunjuk.turunan')">
        @if ($daftar->isEmpty())
            <div class="p-5">
                <x-empty-state :title="__('kalender.keterangan.kelola_kosong')" :description="__('kalender.keterangan.kelola_kosong_deskripsi')" />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.tanggal') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.peristiwa') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.jenis') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.sasaran') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('kalender.kolom.status') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $p)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="tabular whitespace-nowrap px-5 py-3">{{ Format::rentangTanggal($p->tanggal_mulai, $p->tanggal_selesai) }}</td>
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $p->judul }}</div>
                                    <div class="text-[11.5px] text-ink-faint">{{ $p->prodi?->nama ?? __('kalender.keterangan.semua_prodi') }}@if ($p->keterangan) · {{ $p->keterangan }}@endif</div>
                                </td>
                                <td class="px-5 py-3"><x-chip :tone="$p->jenis->tone()">{{ $p->jenis->label() }}</x-chip></td>
                                <td class="px-5 py-3 text-[12.5px]">{{ $p->sasaran->label() }}</td>
                                <td class="px-5 py-3"><x-chip :tone="$p->status->tone()">{{ $p->status->label() }}</x-chip></td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        <x-button href="{{ route('admin.kalender.peristiwa.edit', $p) }}" variant="outline" size="sm">{{ __('kalender.aksi.ubah') }}</x-button>
                                        <form method="POST" action="{{ route('admin.kalender.peristiwa.destroy', $p) }}"
                                            data-confirm="{{ __('kalender.keterangan.konfirmasi_hapus') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger" size="sm">{{ __('kalender.aksi.hapus') }}</x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
