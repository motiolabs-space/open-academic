{{--
    Lembar Beban Kerja Dosen — versi cetak untuk ditandatangani.

    Ditulis untuk dompdf: tanpa flexbox, grid, maupun gradien. Tata letaknya
    bertumpu pada tabel dan border.

    Satu hal yang sengaja dicetak dan tidak lazim ada di borang BKD mana pun:
    kolom "Sumber". Asesor perlu tahu mana baris yang ditarik sistem dari daftar
    kelas — dan dapat diperiksa dalam hitungan detik — dan mana yang diketik
    sendiri oleh yang dinilai, yang buktinya harus dibuka. Tanpa pembedaan itu,
    semua baris sama-sama mencurigakan, dan pada praktiknya tak satu pun
    diperiksa.
--}}
@php
    use App\Enums\UnsurBkd;
    use App\Support\Format;

    $sks = fn (int $ratus): string => number_format($ratus / 100, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BKD — {{ $laporan->dosen->nama }} — {{ $laporan->tahunAkademik->kode }}</title>
    <style>
        @page { margin: 20mm 16mm; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #24262E; margin: 0; }

        .kop { border-bottom: 1.5pt solid #1E2761; padding-bottom: 10pt; margin-bottom: 12pt; }
        .kop-institusi { font-size: 13pt; font-weight: bold; color: #1E2761; letter-spacing: 0.5pt; }
        .kop-sub { font-size: 8pt; color: #6E7078; margin-top: 2pt; }
        .kop-judul { font-size: 11pt; font-weight: bold; color: #1E2761; margin-top: 10pt; letter-spacing: 1.5pt; }

        table { width: 100%; border-collapse: collapse; }

        .biodata td { padding: 2pt 0; font-size: 9pt; vertical-align: top; }
        .biodata .label { width: 24%; color: #6E7078; }
        .biodata .pemisah { width: 3%; }

        .unsur-judul {
            background: #1E2761; color: #FFFFFF; font-size: 8.5pt; font-weight: bold;
            padding: 4pt 8pt; letter-spacing: 0.5pt; margin-top: 12pt;
        }

        .rincian th {
            background: #F4F1E8; border-bottom: 0.8pt solid #C9A961;
            font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4pt;
            padding: 4pt 6pt; text-align: left; color: #6E7078;
        }
        .rincian td { border-bottom: 0.4pt solid #E6E4DC; padding: 3.5pt 6pt; font-size: 8.5pt; vertical-align: top; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .kecil { font-size: 7.5pt; color: #6E7078; }

        .ringkas { margin-top: 14pt; border: 0.8pt solid #1E2761; }
        .ringkas td { padding: 6pt 10pt; font-size: 9pt; }
        .ringkas .angka { font-size: 13pt; font-weight: bold; color: #1E2761; }

        .kesimpulan { margin-top: 12pt; border: 0.8pt solid #C9A961; padding: 8pt 10pt; font-size: 9pt; }

        .ttd { margin-top: 22pt; }
        .ttd td { width: 33%; font-size: 8.5pt; vertical-align: top; padding-right: 8pt; }
        .ttd .ruang { height: 46pt; }

        .catatan-kaki { margin-top: 16pt; font-size: 7.5pt; color: #6E7078; line-height: 1.5; }
    </style>
</head>
<body>

<div class="kop">
    <div class="kop-institusi">{{ Str::upper($institusi) }}</div>
    <div class="kop-sub">Kode Perguruan Tinggi {{ $kodeInstitusi }}</div>
    <div class="kop-judul">LAPORAN BEBAN KERJA DOSEN</div>
</div>

<table class="biodata">
    <tr>
        <td class="label">Nama</td><td class="pemisah">:</td>
        <td>{{ $laporan->dosen->namaLengkap() }}</td>
    </tr>
    <tr>
        <td class="label">NIDN</td><td class="pemisah">:</td>
        <td>{{ $laporan->dosen->nidn ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Program Studi</td><td class="pemisah">:</td>
        <td>{{ $laporan->dosen->prodi?->nama ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Jabatan Fungsional</td><td class="pemisah">:</td>
        <td>{{ $laporan->dosen->jabatan_fungsional ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Semester</td><td class="pemisah">:</td>
        <td>{{ $laporan->tahunAkademik->nama }} ({{ $laporan->tahunAkademik->kode }})</td>
    </tr>
</table>

@foreach (UnsurBkd::cases() as $unsur)
    @php $isi = $baris->get($unsur->value, collect()); @endphp

    <div class="unsur-judul">
        {{ Str::upper($unsur->label()) }}
        — {{ $sks($laporan->{'sks_'.$unsur->value}) }} SKS
    </div>

    <table class="rincian">
        <thead>
            <tr>
                <th style="width: 4%">No</th>
                <th style="width: 56%">Kegiatan</th>
                <th style="width: 18%">Sumber</th>
                <th style="width: 10%" class="kanan">SKS</th>
                <th style="width: 12%" class="tengah">Bukti</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($isi as $i => $b)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>
                        {{ $b->kegiatan }}
                        @if ($b->rincian)
                            <div class="kecil">{{ $b->rincian }}</div>
                        @endif
                    </td>
                    <td class="kecil">
                        {{ $b->otomatis ? 'Rekaman sistem' : 'Dilaporkan sendiri' }}
                    </td>
                    <td class="kanan">{{ $sks($b->sks_ratus) }}</td>
                    <td class="tengah kecil">{{ $b->bukti_path ? 'Ada' : ($b->otomatis ? '—' : 'Tidak ada') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="kecil" style="padding: 6pt">
                        Tidak ada kegiatan yang dilaporkan pada unsur ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endforeach

<table class="ringkas">
    <tr>
        <td>Total Beban Kerja</td>
        <td class="kanan angka">{{ $sks($laporan->sks_total) }} SKS</td>
        <td class="kecil" style="width: 44%">
            Rentang yang ditetapkan kampus:
            {{ $sks($batas['minimum_ratus']) }}–{{ $sks($batas['maksimum_ratus']) }} SKS.
        </td>
    </tr>
</table>

@if ($laporan->kesimpulan !== null)
    <div class="kesimpulan">
        <strong>Kesimpulan asesor:</strong> {{ $laporan->kesimpulan->label() }}
        @if ($laporan->catatan_asesor)
            <div style="margin-top: 4pt">{{ $laporan->catatan_asesor }}</div>
        @endif
    </div>
@endif

<table class="ttd">
    <tr>
        <td>
            Dosen yang bersangkutan,
            <div class="ruang"></div>
            <strong>{{ $laporan->dosen->namaLengkap() }}</strong><br>
            <span class="kecil">NIDN {{ $laporan->dosen->nidn ?? '—' }}</span>
        </td>
        <td>
            Asesor I,
            <div class="ruang"></div>
            <strong>{{ $laporan->asesor1?->namaLengkap() ?? '..............................' }}</strong><br>
            <span class="kecil">NIDN {{ $laporan->asesor1?->nidn ?? '—' }}</span>
        </td>
        <td>
            Asesor II,
            <div class="ruang"></div>
            <strong>{{ $laporan->asesor2?->namaLengkap() ?? '..............................' }}</strong><br>
            <span class="kecil">NIDN {{ $laporan->asesor2?->nidn ?? '—' }}</span>
        </td>
    </tr>
</table>

<div class="catatan-kaki">
    {{-- Dinyatakan, bukan disamarkan: lembar yang belum disahkan memang belum
         menjadi apa-apa, dan mencetaknya tanpa keterangan itu mengundangnya
         dipakai seolah sudah. --}}
    @if ($laporan->disahkan_at !== null)
        Disahkan {{ Format::tanggal($laporan->disahkan_at) }}
        oleh {{ $laporan->pengesah?->nama ?? 'pejabat berwenang' }}.
    @else
        <strong>Belum disahkan.</strong> Lembar ini dicetak dari data
        {{ $institusi }} pada {{ Format::tanggal(now()) }} dan belum
        memiliki kekuatan sebagai laporan resmi.
    @endif

    <br>
    Unsur Pendidikan &amp; Pengajaran ditarik otomatis dari rekaman kelas,
    bimbingan, pengujian, dan perwalian pada semester ini. Unsur lainnya
    dilaporkan sendiri oleh dosen beserta buktinya.
</div>

</body>
</html>
