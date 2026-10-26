@php use App\Support\Format; @endphp
@extends('layouts.app')

@section('title', __('pindahan.judul'))

@section('aksi')
    @can('create', \App\Models\Kemahasiswaan\Mahasiswa::class)
        <x-button href="{{ route('admin.pindahan.create') }}" size="sm">
            + {{ __('pindahan.aksi.daftarkan') }}
        </x-button>
    @endcan
@endsection

@section('content')
    @foreach (['sukses' => 'success', 'galat' => 'danger'] as $kunci => $tone)
        @if (session($kunci))
            <div class="mb-5"><x-alert :tone="$tone">{{ session($kunci) }}</x-alert></div>
        @endif
    @endforeach

    {{-- Pintu pertama: pendaftar yang lulus seleksi di gelombang transfer/RPL.
         Didahulukan karena daftar ulang biasa akan mencatat mereka sebagai
         peserta didik baru. --}}
    <x-card flush class="mb-5" :title="__('pindahan.kartu.menunggu_registrasi')"
        :meta="__('pindahan.kartu.menunggu_registrasi_meta')">
        @if ($menunggu->isEmpty())
            <p class="px-5 py-4 text-[13px] text-ink-muted">{{ __('pindahan.keterangan.tidak_ada_pendaftar') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.pendaftar') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.gelombang') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.prodi') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($menunggu as $p)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $p->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $p->nomor_pendaftaran }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    {{ $p->gelombang->nama }}
                                    <div class="text-[11.5px] text-ink-faint">{{ ucfirst($p->gelombang->jalur) }}</div>
                                </td>
                                <td class="px-5 py-3">{{ $p->prodiDiterima?->nama ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    @can('createFromPmb', \App\Models\Kemahasiswaan\Mahasiswa::class)
                                        <x-button href="{{ route('admin.pindahan.create', ['pendaftar' => $p->uuid]) }}" size="sm">
                                            {{ __('pindahan.aksi.lanjutkan_intake') }}
                                        </x-button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card class="mb-5">
        <form method="GET" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
            <x-field :label="__('pindahan.aksi.cari')" name="cari" :value="$cari" :placeholder="__('pindahan.petunjuk.cari')" />
            <div class="flex items-end"><x-button type="submit">{{ __('pindahan.aksi.cari') }}</x-button></div>
        </form>
    </x-card>

    <x-card flush :title="__('pindahan.kartu.daftar')">
        @if ($daftar->isEmpty())
            <x-empty-state :title="__('pindahan.keterangan.kosong')" :description="__('pindahan.keterangan.kosong_deskripsi')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.mahasiswa') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.jenis_daftar') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('pindahan.kolom.pt_asal') }}</th>
                            <th class="px-5 py-3 text-center font-semibold">{{ __('pindahan.kolom.sks_diakui') }}</th>
                            <th class="px-5 py-3 text-center font-semibold">{{ __('pindahan.kolom.menunggu') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $m)
                            <tr class="border-b border-line/50 last:border-b-0 odd:bg-zebra">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $m->nama }}</div>
                                    <div class="tabular text-[11.5px] text-ink-faint">{{ $m->nim }} · {{ $m->prodi->nama }}</div>
                                </td>
                                <td class="px-5 py-3"><x-chip>{{ $m->jenis_daftar->label() }}</x-chip></td>
                                <td class="px-5 py-3">
                                    {{ $m->pt_asal ?? '—' }}
                                    <div class="text-[11.5px] text-ink-faint">{{ $m->prodi_asal }}</div>
                                </td>
                                <td class="tabular px-5 py-3 text-center">{{ $m->sks_diakui ?? '—' }}</td>
                                <td class="tabular px-5 py-3 text-center">
                                    @if ($m->menunggu_count > 0)
                                        <x-chip tone="warning">{{ Format::bulat($m->menunggu_count) }}</x-chip>
                                    @else
                                        <span class="text-ink-faint">0</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-button href="{{ route('admin.pindahan.show', $m) }}" variant="outline" size="sm">
                                        {{ __('pindahan.aksi.buka') }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-3">{{ $daftar->links() }}</div>
        @endif
    </x-card>
@endsection
