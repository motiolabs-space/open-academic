@php
    use App\Enums\JenisPoin;
    use App\Support\Format;
@endphp
@extends('layouts.app')

@section('title', __('swalayan.prestasi.judul'))

@section('content')
    @include('mahasiswa._swalayan-pesan')

    {{-- Dua buku besar berdampingan, tidak pernah dijumlahkan. --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2">
        <x-stat-card :label="__('swalayan.prestasi.rekap_prestasi')" :value="$rekap['prestasi']"
            :meta="$rekap['minimum'] > 0
                ? __('swalayan.prestasi.minimum', ['minimum' => $rekap['minimum']]).($rekap['memenuhi'] ? ' · '.__('swalayan.prestasi.memenuhi') : '')
                : null" />
        <x-stat-card :label="__('swalayan.prestasi.rekap_pelanggaran')" :value="$rekap['pelanggaran']"
            :meta="$rekap['temuan'] ?? __('swalayan.prestasi.tanpa_temuan')" />
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
        <x-card flush :title="__('swalayan.prestasi.riwayat')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-[11px] uppercase tracking-[0.08em] text-ink-muted">
                            <th class="px-5 py-3 font-semibold">{{ __('swalayan.prestasi.kolom.tanggal') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('swalayan.prestasi.kolom.catatan') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ __('swalayan.prestasi.kolom.poin') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('swalayan.kolom.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayat as $baris)
                            <tr class="border-b border-line/50 align-top last:border-b-0 odd:bg-zebra" data-poin="{{ $baris->uuid }}">
                                <td class="tabular px-5 py-3">{{ Format::tanggal($baris->tanggal) }}</td>
                                <td class="px-5 py-3">
                                    <x-chip :tone="$baris->jenis->tone()">{{ $baris->kategori->nama }}</x-chip>
                                    <div class="mt-1 font-medium">{{ $baris->judul }}</div>
                                    @if ($baris->jenis === JenisPoin::Pelanggaran && $baris->keterangan)
                                        <p class="mt-0.5 max-w-md text-[12px] text-ink-muted">{{ $baris->keterangan }}</p>
                                    @endif
                                    @if ($baris->bukti_path && $baris->dilaporkan_by_mahasiswa_id)
                                        <a href="{{ route('berkas.poin', $baris) }}" class="mt-0.5 inline-block text-[12px] text-navy underline">{{ __('swalayan.unduh_berkas') }}</a>
                                    @endif
                                </td>
                                <td class="tabular px-5 py-3 text-right">{{ $baris->poin }}</td>
                                <td class="px-5 py-3">
                                    @if ($baris->is_verified)
                                        <x-chip tone="success">{{ __('swalayan.prestasi.status.diverifikasi') }}</x-chip>
                                    @elseif ($baris->ditolak())
                                        <x-chip tone="danger">{{ __('swalayan.prestasi.status.ditolak') }}</x-chip>
                                        <p class="mt-1 max-w-xs text-[12px] text-ink-muted">{{ $baris->alasan_tolak }}</p>
                                    @else
                                        <x-chip tone="warning">{{ __('swalayan.prestasi.status.menunggu') }}</x-chip>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state :title="__('swalayan.prestasi.kosong')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="__('swalayan.prestasi.lapor')">
            @if ($kategori->isEmpty())
                <p class="text-[12.5px] text-ink-muted">{{ __('swalayan.prestasi.tanpa_kategori') }}</p>
            @else
                <form method="POST" action="{{ route('mahasiswa.poin.lapor') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <x-field :label="__('swalayan.prestasi.kolom.kategori')" name="kategori" required
                        :options="$kategori->mapWithKeys(fn ($k) => [$k->uuid => $k->nama.' ('.$k->poin.' poin'.($k->wajib_bukti ? ', '.__('swalayan.prestasi.wajib_bukti') : '').')'])" />
                    <x-field :label="__('swalayan.prestasi.kolom.judul')" name="judul" required />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field :label="__('swalayan.prestasi.kolom.tanggal')" name="tanggal" type="date" required />
                        <x-field :label="__('simkatmawa.isian.tanggal_selesai')" name="tanggal_selesai" type="date" />
                    </div>
                    <x-field :label="__('swalayan.kolom.semester')" name="tahun_akademik_id"
                        :options="$daftarTerm->pluck('nama', 'id')" />
                    <x-field :label="__('swalayan.prestasi.kolom.keterangan')" name="keterangan" type="textarea" />

                    @include('mahasiswa._unggah-berkas', ['nama' => 'bukti', 'label' => __('swalayan.prestasi.bukti'), 'wajib' => false])

                    <fieldset class="space-y-3 rounded-card border border-line p-3.5">
                        <legend class="px-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-muted">{{ __('swalayan.prestasi.rincian_simkatmawa') }}</legend>
                        <p class="text-[11.5px] text-ink-faint">{{ __('swalayan.prestasi.rincian_petunjuk') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($pilihan as $nama => $opsi)
                                <x-field :label="__('simkatmawa.isian.'.$nama)" :name="$nama" :options="$opsi" />
                            @endforeach
                        </div>
                        <x-field :label="__('simkatmawa.isian.penyelenggara')" name="penyelenggara" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field :label="__('simkatmawa.isian.jumlah_pt_peserta')" name="jumlah_pt_peserta" type="number" />
                            <x-field :label="__('simkatmawa.isian.jumlah_negara_peserta')" name="jumlah_negara_peserta" type="number" />
                        </div>
                        <x-field :label="__('simkatmawa.isian.nomor_sertifikat')" name="nomor_sertifikat" />
                        <x-field :label="__('simkatmawa.isian.tautan_sertifikat')" name="tautan_sertifikat" type="url" :hint="__('simkatmawa.petunjuk.tautan')" />
                        <x-field :label="__('simkatmawa.isian.tautan_bukti')" name="tautan_bukti" type="url" />
                        <x-field :label="__('simkatmawa.isian.tautan_surat_tugas')" name="tautan_surat_tugas" type="url" />
                    </fieldset>

                    <p class="text-[11.5px] text-ink-faint">{{ __('swalayan.prestasi.aturan') }}</p>
                    <x-button type="submit" class="w-full">{{ __('swalayan.prestasi.tombol') }}</x-button>
                </form>
            @endif
        </x-card>
    </div>
@endsection
