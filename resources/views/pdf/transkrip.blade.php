{{--
    Transkrip akademik resmi.

    Ditulis untuk dompdf, bukan untuk peramban: tanpa flexbox, grid, maupun
    gradien. Tata letaknya bertumpu pada tabel dan border — motif guilloché
    layar diganti bingkai ganda navy-emas yang tercetak bersih di kertas.
--}}
@php use App\Support\Format; @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Transkrip Akademik — {{ $mahasiswa->nim }}</title>
    <style>
        @page { margin: 22mm 18mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            color: #24262E;
            margin: 0;
        }

        .bingkai {
            border: 2.5pt solid #1E2761;
            padding: 3pt;
        }
        .bingkai-dalam { border: 0.8pt solid #C9A961; padding: 14pt 16pt; }

        .kop { border-bottom: 1.5pt solid #1E2761; padding-bottom: 10pt; margin-bottom: 12pt; }
        .kop-institusi { font-size: 14pt; font-weight: bold; color: #1E2761; letter-spacing: 0.5pt; }
        .kop-sub { font-size: 8pt; color: #6E7078; margin-top: 2pt; }
        .kop-judul {
            font-size: 11.5pt; font-weight: bold; color: #1E2761;
            margin-top: 10pt; letter-spacing: 2pt;
        }

        table { width: 100%; border-collapse: collapse; }
        .biodata td { padding: 2pt 0; font-size: 9pt; vertical-align: top; }
        .biodata .label { width: 26%; color: #6E7078; }
        .biodata .pemisah { width: 3%; }

        .semester-judul {
            background: #1E2761; color: #FFFFFF; font-size: 8.5pt; font-weight: bold;
            padding: 4pt 8pt; letter-spacing: 0.5pt;
        }

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
        .verifikasi {
            margin-top: 16pt; border-top: 0.4pt solid #E6E4DC; padding-top: 8pt;
            font-size: 7pt; color: #9A9CA4;
        }
        .kode-verifikasi { font-family: 'DejaVu Sans Mono', monospace; color: #1E2761; font-weight: bold; }
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
            <div class="kop-judul tengah">TRANSKRIP AKADEMIK</div>
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
                <td class="label">Fakultas</td><td class="pemisah">:</td>
                <td>{{ $mahasiswa->prodi->fakultas->nama }}</td>
            </tr>
            <tr>
                <td class="label">Tempat, Tanggal Lahir</td><td class="pemisah">:</td>
                <td>{{ $mahasiswa->tempat_lahir ?? '—' }}, {{ Format::tanggalPanjang($mahasiswa->tanggal_lahir) }}</td>
            </tr>
        </table>

        @forelse ($perSemester as $namaSemester => $daftar)
            <div style="margin-top: 12pt;">
                <div class="semester-judul">{{ Str::upper($namaSemester) }}</div>

                <table class="nilai">
                    <thead>
                        <tr>
                            <th style="width: 13%;">Kode</th>
                            <th>Mata Kuliah</th>
                            <th class="tengah" style="width: 8%;">SKS</th>
                            <th class="tengah" style="width: 10%;">Huruf</th>
                            <th class="tengah" style="width: 10%;">Bobot</th>
                            <th class="kanan" style="width: 11%;">Mutu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $baris)
                            <tr>
                                <td>{{ $baris->kode }}</td>
                                <td>
                                    {{ $baris->nama }}
                                    {{-- Penanda konversi tercetak pada barisnya, bukan hanya
                                         pada catatan kaki. Pembaca luar perlu tahu mata kuliah
                                         mana yang dinilai kampus ini dan mana yang diakui dari
                                         tempat lain — itu pertanyaan pertama mereka. --}}
                                    @if ($baris->konversi)
                                        <sup style="color:#8A6D1F; font-weight:bold;">{{ $baris->tanda }}</sup>
                                    @endif
                                </td>
                                <td class="tengah">{{ $baris->sks }}</td>
                                <td class="tengah"><strong>{{ $baris->huruf ?? '—' }}</strong></td>
                                <td class="tengah">{{ $baris->huruf === null ? '—' : Format::angka($baris->bobot) }}</td>
                                <td class="kanan">{{ $baris->huruf === null ? '—' : Format::angka($baris->mutu()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p style="margin-top: 20pt; font-size: 9pt; color:#6E7078;">
                Belum ada nilai final yang dapat ditranskripkan.
            </p>
        @endforelse

        <table class="ringkas">
            <tr>
                <td style="width: 50%;">
                    Total SKS Ditempuh<br>
                    <span class="angka">{{ $totalSks }}</span>
                </td>
                <td>
                    Indeks Prestasi Kumulatif<br>
                    <span class="angka">{{ Format::angka($ipk) }}</span>
                    @if ($predikat)
                        <span style="font-size:8.5pt; color:#6E7078;">· {{ $predikat }}</span>
                    @endif
                </td>
            </tr>
        </table>

        <table class="ttd">
            <tr>
                <td style="width: 58%;"></td>
                <td>
                    Diterbitkan pada {{ Format::tanggalPanjang($diterbitkan) }}<br>
                    a.n. Rektor,<br>
                    Kepala Biro Administrasi Akademik
                    <div style="height: 44pt;"></div>
                    <div style="border-top: 0.4pt solid #24262E; width: 62%; padding-top: 3pt;">
                        (____________________)
                    </div>
                </td>
            </tr>
        </table>

        {{-- Dinyatakan apa adanya.

             Sebelumnya di sini tercetak "kode verifikasi" beserta kalimat bahwa
             dokumen ini sah tanpa tanda tangan basah bila kodenya cocok dengan
             pangkalan data — padahal tidak ada tempat untuk mencocokkannya.
             Versi bernomor dan dapat diverifikasi adalah Transkrip Legalisir,
             yang diminta lewat portal. --}}
        <div class="verifikasi">
            @if ($adaKonversi)
                {{-- Dinyatakan berapa banyak dan atas dasar apa, bukan sekadar
                     memberi tanda. Angka inilah yang ditimbang pembaca. --}}
                <strong>Kredit alih/rekognisi:</strong>
                {{ $sksKonversi }} SKS diakui dari pembelajaran di luar institusi ini
                (ditandai <sup style="color:#8A6D1F; font-weight:bold;">T</sup> untuk transfer,
                <sup style="color:#8A6D1F; font-weight:bold;">R</sup> untuk rekognisi pembelajaran lampau).
                @if (! config('academic.konversi.hitung_ipk'))
                    Kredit tersebut dihitung ke dalam total SKS, tetapi tidak ke dalam IPK —
                    IPK mencerminkan penilaian institusi ini.
                @endif
                <br>
            @endif
            <strong>Salinan tidak resmi.</strong> Lembar ini dicetak sendiri dari portal akademik
            dan tidak bernomor, sehingga keasliannya tidak dapat diperiksa pihak ketiga.
            Untuk keperluan resmi, mintalah <strong>Transkrip Legalisir</strong> melalui portal —
            dokumen tersebut bernomor dan dapat diverifikasi di
            <span class="kode-verifikasi">{{ $tautanVerifikasi }}</span>.<br>
            Hanya memuat nilai yang telah difinalisasi
            · Mata kuliah yang diulang ditampilkan dengan perolehan terbaik.
        </div>

    </div>
</div>
</body>
</html>
