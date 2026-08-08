@php use App\Support\Format; @endphp
@extends('pdf.surat.layout')

@section('judul', 'Transkrip Akademik')

@section('isi')
    <table class="biodata">
        <tr><td class="label">Nama</td><td class="pemisah">:</td><td><strong>{{ $isi['nama'] }}</strong></td></tr>
        <tr><td class="label">Nomor Induk Mahasiswa</td><td class="pemisah">:</td><td>{{ $isi['nim'] }}</td></tr>
        <tr><td class="label">Program Studi</td><td class="pemisah">:</td><td>{{ $isi['jenjang'] }} {{ $isi['prodi'] }}</td></tr>
    </table>

    {{-- Satu-satunya bagian yang dirakit ulang saat cetak, bukan dari snapshot.
         Daftar mata kuliah adalah laporan atas catatan, bukan pernyataan tentang
         satu saat — mencetak ulang versi yang melewatkan koreksi nilai berarti
         mencetak ulang kesalahan. Lihat SuratPdfService. --}}
    @foreach ($transkrip['perSemester'] as $semester => $baris)
        <table class="tabel-isi" style="margin-top: 12pt;">
            <thead>
                <tr>
                    <th style="width: 14%;">Kode</th>
                    <th>Mata Kuliah</th>
                    <th style="width: 8%; text-align: center;">SKS</th>
                    <th style="width: 10%; text-align: center;">Nilai</th>
                    <th style="width: 10%; text-align: center;">Bobot</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($baris as $n)
                    <tr>
                        <td>{{ $n->kode }}</td>
                        <td>
                            {{ $n->nama }}
                            @if ($n->konversi)
                                <sup style="color:#8A6D1F; font-weight:bold;">{{ $n->tanda }}</sup>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ $n->sks }}</td>
                        <td style="text-align: center;">{{ $n->huruf ?? '—' }}</td>
                        <td style="text-align: center;">{{ $n->huruf === null ? '—' : Format::angka($n->bobot) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <table class="biodata" style="margin-top: 14pt; border-top: 1pt solid #1E2761; padding-top: 8pt;">
        <tr><td class="label">Total SKS Lulus</td><td class="pemisah">:</td><td><strong>{{ $transkrip['totalSks'] }} SKS</strong></td></tr>
        <tr><td class="label">Indeks Prestasi Kumulatif</td><td class="pemisah">:</td><td><strong>{{ Format::angka($transkrip['ipk']) }}</strong></td></tr>
    </table>

    <p class="catatan-kecil">
        Transkrip ini memuat nilai yang telah difinalisasi. Untuk mata kuliah yang
        diulang, ditampilkan hasil terbaik — aturan yang sama dengan perhitungan IPK.
        @if ($transkrip['adaKonversi'])
            Tanda <sup style="color:#8A6D1F; font-weight:bold;">T</sup> menandai kredit alih dari
            perguruan tinggi lain dan <sup style="color:#8A6D1F; font-weight:bold;">R</sup> menandai
            rekognisi pembelajaran lampau, seluruhnya {{ $transkrip['sksKonversi'] }} SKS.
        @endif
    </p>
@endsection
