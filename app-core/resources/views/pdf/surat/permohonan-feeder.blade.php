{{--
    Surat permohonan PDM/PJK ke PDDIKTI admin (#27b).

    Seluruh isinya dari konten yang dibekukan saat terbit: kolom, nilai lama,
    nilai baru, alasan, dan lampiran. Nilai lama ditulis sebagaimana tercatat
    di PDDIKTI — itulah yang dicari petugas di layarnya — bukan sebagaimana
    tercatat di sini sekarang.
--}}
@php use App\Support\Format; @endphp
@extends('pdf.surat.layout')

@section('isi')
    <p class="paragraf" style="margin-bottom: 2pt;">
        {{ __('surat.permohonan_feeder.kepada') }}<br>
        <strong>{{ $isi['tujuan'] }}</strong><br>
        {{ __('surat.permohonan_feeder.di_tempat') }}
    </p>

    <p class="paragraf">
        {{ __('surat.permohonan_feeder.pembuka', ['jenis' => $isi['jenis_perubahan']]) }}
    </p>

    <table class="biodata">
        <tr><td class="label">Nama</td><td class="pemisah">:</td><td><strong>{{ $isi['nama'] }}</strong></td></tr>
        <tr><td class="label">Nomor Induk Mahasiswa</td><td class="pemisah">:</td><td>{{ $isi['nim'] }}</td></tr>
        <tr><td class="label">Program Studi</td><td class="pemisah">:</td><td>{{ $isi['jenjang'] }} {{ $isi['prodi'] }}</td></tr>
        @if (!empty($isi['angkatan']))
            <tr><td class="label">Angkatan</td><td class="pemisah">:</td><td>{{ $isi['angkatan'] }}</td></tr>
        @endif
    </table>

    <p class="paragraf">{{ __('surat.permohonan_feeder.pengantar_tabel') }}</p>

    <table class="tabel-isi">
        <thead>
            <tr>
                <th style="width: 6%;">{{ __('surat.permohonan_feeder.kolom.no') }}</th>
                <th style="width: 20%;">{{ __('surat.permohonan_feeder.kolom.data') }}</th>
                <th style="width: 22%;">{{ __('surat.permohonan_feeder.kolom.lama') }}</th>
                <th style="width: 22%;">{{ __('surat.permohonan_feeder.kolom.baru') }}</th>
                <th>{{ __('surat.permohonan_feeder.kolom.alasan') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($isi['perubahan'] ?? [] as $i => $baris)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $baris['label'] }}</td>
                    <td>{{ $baris['lama'] ?? __('surat.permohonan_feeder.kosong_nilai') }}</td>
                    <td><strong>{{ $baris['baru'] ?? __('surat.permohonan_feeder.kosong_nilai') }}</strong></td>
                    <td>{{ $baris['alasan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="paragraf">{{ __('surat.permohonan_feeder.lampiran_judul') }}</p>
    <ol style="margin: 0 0 0 14pt; padding: 0; font-size: 9.5pt;">
        @foreach ($isi['lampiran'] ?? [] as $lampiran)
            <li>{{ $lampiran }}</li>
        @endforeach
    </ol>

    <p class="paragraf">{{ __('surat.permohonan_feeder.penutup') }}</p>
@endsection
