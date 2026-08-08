@php use App\Support\Format; @endphp
@extends('pdf.surat.layout')

@section('isi')
    <p class="paragraf">
        Yang bertanda tangan di bawah ini menerangkan bahwa:
    </p>

    <table class="biodata">
        <tr><td class="label">Nama</td><td class="pemisah">:</td><td><strong>{{ $isi['nama'] }}</strong></td></tr>
        <tr><td class="label">Nomor Induk Mahasiswa</td><td class="pemisah">:</td><td>{{ $isi['nim'] }}</td></tr>
        @if (!empty($isi['tempat_lahir']))
            <tr>
                <td class="label">Tempat, Tanggal Lahir</td><td class="pemisah">:</td>
                <td>{{ $isi['tempat_lahir'] }}, {{ Format::tanggalPanjang($isi['tanggal_lahir']) }}</td>
            </tr>
        @endif
        <tr><td class="label">Program Studi</td><td class="pemisah">:</td><td>{{ $isi['jenjang'] }} {{ $isi['prodi'] }}</td></tr>
        @if (!empty($isi['fakultas']))
            <tr><td class="label">Fakultas</td><td class="pemisah">:</td><td>{{ $isi['fakultas'] }}</td></tr>
        @endif
        <tr><td class="label">Tahun Angkatan</td><td class="pemisah">:</td><td>{{ $isi['angkatan'] }}</td></tr>
    </table>

    <p class="paragraf">
        adalah benar mahasiswa {{ $isi['institusi'] }} yang berstatus
        <strong>{{ strtolower($isi['status'] ?? 'aktif') }}</strong>
        @if (!empty($isi['tahun_akademik']))
            pada {{ $isi['tahun_akademik'] }}
        @endif
        @if (!empty($isi['semester_ke']))
            dan sedang menempuh semester ke-{{ $isi['semester_ke'] }}
        @endif
        pada program studi tersebut di atas.
    </p>

    <p class="paragraf">
        Surat keterangan ini diberikan untuk dipergunakan sebagaimana mestinya.
    </p>

    {{-- Dinyatakan terbuka pada dokumennya, bukan hanya pada halaman verifikasi.
         Surat semacam ini kerap disimpan berbulan-bulan lalu diajukan kembali. --}}
    <p class="catatan-kecil">
        Keterangan status di atas menggambarkan keadaan pada tanggal penerbitan.
    </p>
@endsection
