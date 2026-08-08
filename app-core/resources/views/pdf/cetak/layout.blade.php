{{--
    Kerangka bersama untuk dokumen cetak rutin: KTM, kartu ujian, daftar hadir,
    dan jurnal perkuliahan.

    Ditulis untuk dompdf, bukan peramban — tanpa flexbox, grid, atau gradien.
    Tata letaknya bertumpu pada tabel dan border.

    Kop, judul, penandatangan, dan catatan kaki datang dari $dok
    (PengaturanDokumen::untuk). Halaman ini yang menentukan bentuknya; kampus
    hanya menentukan isinya.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dok['judul'] }}@isset($subjudul) — {{ $subjudul }}@endisset</title>
    <style>
        @page { margin: {{ $margin ?? '16mm 14mm' }}; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #24262E;
            margin: 0;
        }

        table { width: 100%; border-collapse: collapse; }

        .kop { border-bottom: 1.5pt solid #1E2761; padding-bottom: 8pt; margin-bottom: 10pt; }
        .kop-logo { width: 46pt; vertical-align: middle; }
        .kop-logo img { width: 40pt; height: 40pt; }
        .kop-institusi { font-size: 13pt; font-weight: bold; color: #1E2761; }
        .kop-sub { font-size: 7.5pt; color: #6E7078; margin-top: 1.5pt; }

        .judul {
            text-align: center; font-size: 11pt; font-weight: bold;
            color: #1E2761; letter-spacing: 1.5pt; margin-bottom: 3pt;
        }
        .subjudul { text-align: center; font-size: 8.5pt; color: #6E7078; margin-bottom: 10pt; }

        .meta td { padding: 1.5pt 0; font-size: 8.5pt; vertical-align: top; }
        .meta .label { width: 22%; color: #6E7078; }
        .meta .pemisah { width: 2%; }

        .daftar th {
            background: #F4F1E8; border: 0.6pt solid #C9A961;
            font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4pt;
            padding: 4pt 5pt; text-align: left;
        }
        .daftar td { border: 0.6pt solid #D8D6CE; padding: 4pt 5pt; font-size: 8.5pt; }
        .daftar .nomor { width: 22pt; text-align: center; color: #6E7078; }
        .tengah { text-align: center; }
        .tabular { font-variant-numeric: tabular-nums; }

        /* Kolom tanda tangan sengaja dibiarkan tinggi dan kosong. */
        .ttd-kolom { height: 22pt; }

        .ttd-blok { margin-top: 18pt; width: 100%; }
        .ttd-blok td { font-size: 8.5pt; vertical-align: top; }
        .ttd-ruang { height: 46pt; }
        .ttd-nama { font-weight: bold; border-top: 0.6pt solid #24262E; padding-top: 2pt; }

        .kaki {
            margin-top: 12pt; border-top: 0.6pt solid #D8D6CE; padding-top: 5pt;
            font-size: 7pt; color: #6E7078; line-height: 1.4;
        }
    </style>
    @stack('gaya')
</head>
<body>

<table class="kop">
    <tr>
        @if ($dok['logo'])
            <td class="kop-logo"><img src="{{ $dok['logo'] }}" alt=""></td>
        @endif
        <td>
            <div class="kop-institusi">{{ $dok['institusi'] }}</div>
            @if ($dok['alamat'])
                <div class="kop-sub">{{ $dok['alamat'] }}</div>
            @endif
            @if ($dok['kontak'])
                <div class="kop-sub">{{ $dok['kontak'] }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="judul">{{ $dok['judul'] }}</div>
@isset($subjudul)
    <div class="subjudul">{{ $subjudul }}</div>
@endisset

@yield('isi')

{{-- $blokSendiri: dokumen yang menaruh penandatangan dan catatan kakinya
     sendiri di dalam tata letaknya (KTM menaruhnya di sisi belakang kartu).
     Tanpa ini keduanya tercetak dua kali. --}}
@if (!($blokSendiri ?? false) && $dok['bertanda_tangan'] && $dok['penandatangan'])
    {{-- Hanya dicetak untuk dokumen yang memang punya penandatangan tetap.
         Absensi dan jurnal ditandatangani oleh yang hadir. --}}
    <table class="ttd-blok">
        <tr>
            <td style="width: 60%"></td>
            <td>
                <div>{{ $tanggalTtd ?? now()->translatedFormat('d F Y') }}</div>
                <div>{{ $dok['penandatangan']['jabatan'] }}</div>
                <div class="ttd-ruang"></div>
                <div class="ttd-nama">
                    {{ $dok['penandatangan']['nama'] !== '' ? $dok['penandatangan']['nama'] : '.....................................' }}
                </div>
                @if ($dok['penandatangan']['nip'] !== '')
                    <div>NIP {{ $dok['penandatangan']['nip'] }}</div>
                @endif
            </td>
        </tr>
    </table>
@endif

@if (!($blokSendiri ?? false) && $dok['catatan_kaki'])
    <div class="kaki">{{ $dok['catatan_kaki'] }}</div>
@endif

</body>
</html>
