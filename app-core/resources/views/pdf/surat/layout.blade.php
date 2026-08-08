{{--
    Kerangka bersama seluruh surat resmi.

    Ditulis untuk dompdf, bukan peramban: tanpa flexbox, grid, maupun gradien.
    Tata letaknya bertumpu pada tabel dan border, sejalan dengan pdf/transkrip.

    Satu berkas untuk semua jenis, supaya kop, blok tanda tangan, dan panel
    verifikasi tidak berkembang menjadi lima salinan yang perlahan berbeda —
    dan supaya perbaikan pada satu surat tidak diam-diam melewatkan empat
    lainnya.
--}}
@php use App\Support\Format; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $surat->jenis->label() }} — {{ $surat->nomor }}</title>
    <style>
        @page { margin: 20mm 18mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #24262E;
            margin: 0;
            line-height: 1.55;
        }

        .kop {
            border-bottom: 1.5pt solid #1E2761;
            padding-bottom: 8pt;
            margin-bottom: 4pt;
        }
        .kop-institusi { font-size: 15pt; font-weight: bold; color: #1E2761; letter-spacing: 0.5pt; }
        .kop-sub { font-size: 8pt; color: #6E7078; margin-top: 2pt; }
        .kop-garis-emas { border-top: 2.5pt solid #C9A961; margin-bottom: 16pt; }

        .judul {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            color: #1E2761;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
        }
        .nomor { text-align: center; font-size: 9.5pt; color: #6E7078; margin-top: 2pt; margin-bottom: 16pt; }

        table { width: 100%; border-collapse: collapse; }
        .biodata td { padding: 2.5pt 0; font-size: 10pt; vertical-align: top; }
        .biodata .label { width: 30%; color: #6E7078; }
        .biodata .pemisah { width: 3%; }

        .paragraf { margin: 10pt 0; text-align: justify; }

        .ttd { margin-top: 26pt; }
        .ttd-kolom { width: 45%; font-size: 9.5pt; }
        .ttd-ruang { height: 46pt; }
        .ttd-nama { font-weight: bold; border-bottom: 0.5pt solid #24262E; display: inline-block; padding-bottom: 1pt; }
        .ttd-nip { font-size: 8.5pt; color: #6E7078; }

        .verifikasi {
            margin-top: 20pt;
            border: 0.8pt solid #C9A961;
            background: #FBF9F4;
            padding: 8pt 10pt;
            font-size: 7.5pt;
            color: #4A4C55;
        }
        .verifikasi-qr { width: 78pt; vertical-align: top; }
        .verifikasi td { vertical-align: top; }
        .verifikasi .tautan { font-family: 'DejaVu Sans Mono', monospace; font-size: 7pt; word-break: break-all; }

        .tabel-isi th {
            background: #F4F1E8; border-bottom: 0.8pt solid #C9A961;
            font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt;
            padding: 4pt 6pt; text-align: left;
        }
        .tabel-isi td { padding: 3.5pt 6pt; font-size: 8.5pt; border-bottom: 0.4pt solid #E6E3DA; }

        .catatan-kecil { font-size: 8pt; color: #6E7078; margin-top: 10pt; font-style: italic; }
    </style>
</head>
<body>
    <div class="kop">
        <table>
            <tr>
                @if ($logo)
                    <td style="width: 52pt;"><img src="{{ $logo }}" style="width: 44pt;" alt=""></td>
                @endif
                <td>
                    <div class="kop-institusi">{{ $isi['institusi'] ?? '' }}</div>
                    <div class="kop-sub">
                        {{ $isi['fakultas'] ?? '' }}@if (!empty($isi['fakultas'])) · @endif
                        Kode Perguruan Tinggi {{ $isi['kode_institusi'] ?? '—' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <div class="kop-garis-emas"></div>

    <div class="judul">@yield('judul', $surat->jenis->label())</div>
    <div class="nomor">Nomor: {{ $surat->nomor }}</div>

    @yield('isi')

    {{-- Blok tanda tangan. Nama pejabat dikosongkan bila tidak dikonfigurasi:
         nama orang yang sudah tidak menjabat lebih buruk daripada nama kantor. --}}
    <table class="ttd">
        <tr>
            <td style="width: 55%;"></td>
            <td class="ttd-kolom">
                {{ Format::tanggalPanjang($surat->diterbitkan_at) }}<br>
                {{ $isi['penandatangan']['jabatan'] ?? '' }}
                <div class="ttd-ruang"></div>
                <span class="ttd-nama">
                    {{ $isi['penandatangan']['nama'] ?? $isi['institusi'] ?? '' }}
                </span>
                @if (!empty($isi['penandatangan']['nip']))
                    <div class="ttd-nip">NIP. {{ $isi['penandatangan']['nip'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Panel verifikasi. QR memuat URL saja — bukan isinya — supaya yang
         menjawab keasliannya adalah kampus, bukan kertasnya sendiri. --}}
    <table class="verifikasi">
        <tr>
            @if ($qr)
                <td class="verifikasi-qr"><img src="{{ $qr }}" style="width: 72pt;" alt=""></td>
            @endif
            <td>
                <strong>Keaslian dokumen ini dapat diperiksa.</strong><br>
                @if ($qr)
                    Pindai kode di samping, atau buka:
                @else
                    Buka alamat berikut:
                @endif
                <span class="tautan">{{ $tautanVerifikasi }}</span><br>
                Dapat juga diperiksa manual dengan memasukkan nomor surat dan NIM
                pada halaman verifikasi.
                @if ($surat->berlaku_sampai)
                    <br><strong>Berlaku sampai {{ Format::tanggalPanjang($surat->berlaku_sampai) }}.</strong>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
