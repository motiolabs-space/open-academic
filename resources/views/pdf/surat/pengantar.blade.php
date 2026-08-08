@extends('pdf.surat.layout')

@section('isi')
    <p class="paragraf">
        Dengan hormat, bersama surat ini kami menerangkan bahwa mahasiswa berikut
        adalah benar mahasiswa aktif {{ $isi['institusi'] }}:
    </p>

    <table class="biodata">
        <tr><td class="label">Nama</td><td class="pemisah">:</td><td><strong>{{ $isi['nama'] }}</strong></td></tr>
        <tr><td class="label">Nomor Induk Mahasiswa</td><td class="pemisah">:</td><td>{{ $isi['nim'] }}</td></tr>
        <tr><td class="label">Program Studi</td><td class="pemisah">:</td><td>{{ $isi['jenjang'] }} {{ $isi['prodi'] }}</td></tr>
        @if (!empty($isi['fakultas']))
            <tr><td class="label">Fakultas</td><td class="pemisah">:</td><td>{{ $isi['fakultas'] }}</td></tr>
        @endif
    </table>

    {{-- Keperluan adalah isi surat ini, bukan lampirannya. Karena itu
         JenisSurat::perluKeperluan() menolak permohonan tanpa kalimat ini. --}}
    <p class="paragraf">
        Yang bersangkutan bermaksud melaksanakan keperluan sebagai berikut:
    </p>

    <p class="paragraf" style="padding-left: 16pt; border-left: 2pt solid #C9A961;">
        {{ $isi['keperluan'] }}
    </p>

    <p class="paragraf">
        Sehubungan dengan hal tersebut, kami mohon kesediaan Bapak/Ibu untuk
        memberikan izin dan bantuan yang diperlukan kepada yang bersangkutan.
        Atas perhatian dan kerja samanya kami ucapkan terima kasih.
    </p>
@endsection
