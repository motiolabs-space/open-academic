{{--
    Kartu Hasil Studi satu semester.

    Satu sumber untuk mahasiswa dan staf (KhsService). Gaya cetaknya sama dengan
    transkrip: dompdf, tabel dan border, tanpa flexbox maupun grid.
--}}
@php use App\Support\Format; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Hasil Studi — {{ $mahasiswa->nim }} — {{ $status->tahunAkademik->kode }}</title>
    <style>
        @page { margin: 22mm 18mm; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #24262E; margin: 0; }

        .bingkai { border: 2.5pt solid #1E2761; padding: 3pt; }
        .bingkai-dalam { border: 0.8pt solid #C9A961; padding: 14pt 16pt; }

        .kop { border-bottom: 1.5pt solid #1E2761; padding-bottom: 10pt; margin-bottom: 12pt; }
        .kop-institusi { font-size: 14pt; font-weight: bold; color: #1E2761; letter-spacing: 0.5pt; }
        .kop-sub { font-size: 8pt; color: #6E7078; margin-top: 2pt; }
        .kop-judul { font-size: 11.5pt; font-weight: bold; color: #1E2761; margin-top: 10pt; letter-spacing: 2pt; }

        table { width: 100%; border-collapse: collapse; }
        .biodata td { padding: 2pt 0; font-size: 9pt; vertical-align: top; }
        .biodata .label { width: 26%; color: #6E7078; }
        .biodata .pemisah { width: 3%; }

        .nilai { margin-top: 12pt; }
        .nilai th {
            background: #F4F1E8; border-bottom: 0.8pt solid #C9A961;
            font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt;
            padding: 4pt 6pt; text-align: left; color: #6E7078;
        }
        .nilai td { border-bottom: 0.4pt solid #E6E4DC; padding: 3.5pt 6pt; font-size: 8.5pt; }
        .tengah { text-align: center; }
        .kanan { text-align: right; }

        .ringkas { margin-top: 14pt; border: 0.8pt solid #1E2761; }
        .ringkas td { padding: 7pt 10pt; font-size: 9.5pt; }
        .ringkas .angka { font-size: 15pt; font-weight: bold; color: #1E2761; }

        .ttd { margin-top: 26pt; font-size: 8.5pt; }
        .catatan { margin-top: 16pt; border-top: 0.4pt solid #E6E4DC; padding-top: 8pt; font-size: 7pt; color: #9A9CA4; }
    </style>
</head>
<body>
<div class="bingkai">
    <div class="bingkai-dalam">

        <div class="kop">
            <table>
                <tr>
                    <td>
                        <div class="kop-institusi">{{ Str::upper($institusi) }}</div>
                        <div class="kop-sub">Kode Perguruan Tinggi {{ $kodeInstitusi }}</div>
                    </td>
                    <td class="kanan" style="width: 22%; color:#C9A961; font-size:22pt; font-weight:bold;">◆</td>
                </tr>
            </table>
            <div class="kop-judul tengah">KARTU HASIL STUDI</div>
        </div>

        <table class="biodata">
            <tr>
                <td class="label">Nama Mahasiswa</td><td class="pemisah">:</td>
                <td><strong>{{ $mahasiswa->nama }}</strong></td>
            </tr>
            <tr>
                <td class="label">Nomor Induk Mahasiswa</td><td class="pemisah">:</td>
                <td>{{ $mahasiswa->nim }}</td>
            </tr>
            <tr>
                <td class="label">Program Studi</td><td class="pemisah">:</td>
                <td>{{ $mahasiswa->prodi->namaLengkap() }}</td>
            </tr>
            <tr>
                <td class="label">Semester</td><td class="pemisah">:</td>
                <td>{{ $status->tahunAkademik->nama }} (semester ke-{{ $status->semester_ke }})</td>
            </tr>
            <tr>
                <td class="label">Dosen Wali</td><td class="pemisah">:</td>
                <td>{{ $mahasiswa->dosenWali?->namaLengkap() ?? '—' }}</td>
            </tr>
        </table>

        <table class="nilai">
            <thead>
                <tr>
                    <th style="width: 13%;">Kode</th>
                    <th>Mata Kuliah</th>
                    <th class="tengah" style="width: 8%;">SKS</th>
                    <th class="tengah" style="width: 10%;">Angka</th>
                    <th class="tengah" style="width: 10%;">Huruf</th>
                    <th class="kanan" style="width: 11%;">Mutu</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($nilai as $baris)
                    <tr>
                        <td>{{ $baris->kelasKuliah->mataKuliah->kode }}</td>
                        <td>{{ $baris->kelasKuliah->mataKuliah->nama }}</td>
                        <td class="tengah">{{ $baris->krsDetail->sks }}</td>
                        <td class="tengah">{{ Format::angka($baris->nilai_angka) }}</td>
                        <td class="tengah"><strong>{{ $baris->nilai_huruf?->value ?? '—' }}</strong></td>
                        <td class="kanan">{{ Format::angka($baris->mutu()) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="color:#6E7078;">Tidak ada nilai final pada semester ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="ringkas">
            <tr>
                <td style="width: 33%;">
                    SKS Semester<br>
                    <span class="angka">{{ $status->sks_semester }}</span>
                </td>
                <td style="width: 33%;">
                    Indeks Prestasi Semester<br>
                    <span class="angka">{{ Format::angka($status->ips) }}</span>
                </td>
                <td>
                    Indeks Prestasi Kumulatif<br>
                    <span class="angka">{{ Format::angka($status->ipk) }}</span>
                </td>
            </tr>
        </table>

        <table class="ttd">
            <tr>
                <td style="width: 58%;"></td>
                <td>
                    Dicetak pada {{ Format::tanggalPanjang($diterbitkan) }}<br>
                    Dosen Wali,
                    <div style="height: 44pt;"></div>
                    <div style="border-top: 0.4pt solid #24262E; width: 62%; padding-top: 3pt;">
                        {{ $mahasiswa->dosenWali?->namaLengkap() ?? '(____________________)' }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="catatan">
            Kartu ini salinan cetak dari nilai final yang tercatat pada sistem akademik; IPS dan IPK dibaca
            dari catatan semester yang telah ditutup.
        </div>
    </div>
</div>
</body>
</html>
