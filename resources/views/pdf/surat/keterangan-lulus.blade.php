@php use App\Support\Format; @endphp
@extends('pdf.surat.layout')

@section('isi')
    <p class="paragraf">
        Yang bertanda tangan di bawah ini menerangkan bahwa:
    </p>

    <table class="biodata">
        <tr><td class="label">Nama</td><td class="pemisah">:</td><td><strong>{{ $isi['nama'] }}</strong></td></tr>
        <tr><td class="label">Nomor Induk Mahasiswa</td><td class="pemisah">:</td><td>{{ $isi['nim'] }}</td></tr>
        <tr><td class="label">Program Studi</td><td class="pemisah">:</td><td>{{ $isi['jenjang'] }} {{ $isi['prodi'] }}</td></tr>
        @if (!empty($isi['fakultas']))
            <tr><td class="label">Fakultas</td><td class="pemisah">:</td><td>{{ $isi['fakultas'] }}</td></tr>
        @endif
    </table>

    <p class="paragraf">
        telah dinyatakan <strong>LULUS</strong> dari {{ $isi['institusi'] }} pada
        {{ Format::tanggalPanjang($isi['tanggal_lulus']) }}
        @if (!empty($isi['nomor_sk']))
            berdasarkan Surat Keputusan Nomor {{ $isi['nomor_sk'] }}
        @endif
        dengan hasil sebagai berikut:
    </p>

    <table class="biodata">
        <tr><td class="label">Total SKS</td><td class="pemisah">:</td><td>{{ $isi['total_sks'] }} SKS</td></tr>
        <tr><td class="label">Indeks Prestasi Kumulatif</td><td class="pemisah">:</td><td>{{ Format::angka($isi['ipk']) }}</td></tr>
        <tr><td class="label">Predikat Kelulusan</td><td class="pemisah">:</td><td>{{ $isi['predikat'] }}</td></tr>
        @if (!empty($isi['judul_tugas_akhir']))
            <tr><td class="label">Judul Tugas Akhir</td><td class="pemisah">:</td><td>{{ $isi['judul_tugas_akhir'] }}</td></tr>
        @endif
    </table>

    {{-- Alasan surat ini ada: ijazah butuh berbulan-bulan, sedangkan pemberi
         kerja butuh bukti sekarang. Menyebutkannya menjelaskan kedudukan
         dokumen ini kepada pembacanya. --}}
    <p class="paragraf">
        Surat keterangan ini diterbitkan sebagai bukti kelulusan sementara sampai
        ijazah yang bersangkutan diterbitkan, dan berlaku sebagai dokumen yang sah
        untuk keperluan tersebut.
    </p>
@endsection
