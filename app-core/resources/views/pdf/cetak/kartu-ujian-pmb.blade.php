{{--
    Kartu ujian seleksi PMB: identitas peserta, pilihan prodi, jadwal seleksi.

    Tanpa NIK dan tanpa alamat — kartu ini dibawa ke ruang ujian, difoto, dan
    hilang. Nomor pendaftaran ada di kartu dan di QR karena itulah yang
    dicocokkan pengawas dengan lembar nilai CBT yang kelak diimpor.
--}}
@extends('pdf.cetak.layout')

@push('gaya')
    <style>
        .identitas { margin-bottom: 10pt; }
        .identitas td { vertical-align: top; }
        .qr { width: 70pt; text-align: right; }
    </style>
@endpush

@section('isi')

    <table class="identitas">
        <tr>
            <td>
                <table class="meta">
                    <tr>
                        <td class="label">{{ __('pmb.kartu.kolom.nomor') }}</td><td class="pemisah">:</td>
                        <td class="tabular"><strong>{{ $pendaftar->nomor_pendaftaran }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">{{ __('pmb.kartu.kolom.nama') }}</td><td class="pemisah">:</td>
                        <td><strong>{{ $pendaftar->nama }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">{{ __('pmb.kartu.kolom.ttl') }}</td><td class="pemisah">:</td>
                        <td>{{ $pendaftar->tempat_lahir }}, {{ \App\Support\Format::tanggalPanjang($pendaftar->tanggal_lahir) }}</td>
                    </tr>
                    <tr>
                        <td class="label">{{ __('pmb.kartu.kolom.pilihan_1') }}</td><td class="pemisah">:</td>
                        <td>{{ $pendaftar->prodiPilihan1?->nama }}</td>
                    </tr>
                    @if ($pendaftar->prodiPilihan2)
                        <tr>
                            <td class="label">{{ __('pmb.kartu.kolom.pilihan_2') }}</td><td class="pemisah">:</td>
                            <td>{{ $pendaftar->prodiPilihan2->nama }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="label">{{ __('pmb.kartu.kolom.jalur') }}</td><td class="pemisah">:</td>
                        <td>{{ $pendaftar->gelombang->jalur?->label() }}</td>
                    </tr>
                </table>
            </td>
            <td class="qr">
                @if ($qr)
                    <img src="{{ $qr }}" alt="" style="width: 60pt; height: 60pt;">
                @endif
            </td>
        </tr>
    </table>

    <table class="daftar">
        <thead>
            <tr>
                <th class="nomor">{{ __('pmb.kartu.kolom.no') }}</th>
                <th>{{ __('pmb.kartu.kolom.kegiatan') }}</th>
                <th style="width: 120pt">{{ __('pmb.kartu.kolom.waktu') }}</th>
                <th style="width: 110pt">{{ __('pmb.kartu.kolom.lokasi') }}</th>
                <th style="width: 80pt">{{ __('pmb.kartu.kolom.paraf') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($jadwal as $i => $j)
                <tr>
                    <td class="nomor">{{ $i + 1 }}</td>
                    <td>{{ $j->nama }}</td>
                    <td class="tabular">
                        {{ \App\Support\Format::tanggal($j->mulai) }},
                        {{ \App\Support\Format::rentangJam($j->mulai, $j->selesai) }}
                    </td>
                    <td>{{ $j->lokasi ?? '—' }}</td>
                    <td class="ttd-kolom"></td>
                </tr>
            @empty
                <tr><td colspan="5" class="tengah">{{ __('pmb.kartu.tanpa_jadwal') }}</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
