@php use App\Support\Format; @endphp
@extends('pdf.surat.layout')

@section('judul', 'Surat Keterangan Pendamping Ijazah')

@section('isi')
    {{-- Dwibahasa sebagaimana diamanatkan regulasi. Bahasa Inggris ditempatkan
         berdampingan, bukan pada halaman terpisah: halaman terpisah adalah yang
         hilang saat dokumen difotokopi. --}}
    <div style="text-align: center; font-size: 9pt; color: #6E7078; margin-top: -12pt; margin-bottom: 14pt;">
        <em>Diploma Supplement</em>
    </div>

    <div class="judul" style="font-size: 9.5pt; letter-spacing: 1pt; text-align: left; margin-bottom: 6pt;">
        1. Identitas Pemegang Ijazah <span style="color: #6E7078;">/ Holder of the Qualification</span>
    </div>

    <table class="biodata">
        <tr><td class="label">Nama <em>/ Name</em></td><td class="pemisah">:</td><td><strong>{{ $isi['nama'] }}</strong></td></tr>
        <tr><td class="label">NIM <em>/ Student Number</em></td><td class="pemisah">:</td><td>{{ $isi['nim'] }}</td></tr>
        @if (!empty($isi['tempat_lahir']))
            <tr>
                <td class="label">Tempat, Tanggal Lahir <em>/ Place, Date of Birth</em></td>
                <td class="pemisah">:</td>
                <td>{{ $isi['tempat_lahir'] }}, {{ Format::tanggalPanjang($isi['tanggal_lahir']) }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Tanggal Lulus <em>/ Date of Graduation</em></td><td class="pemisah">:</td>
            <td>{{ Format::tanggalPanjang($isi['tanggal_lulus']) }}</td>
        </tr>
        @if (!empty($isi['nomor_ijazah']))
            <tr><td class="label">Nomor Ijazah <em>/ Diploma Number</em></td><td class="pemisah">:</td><td>{{ $isi['nomor_ijazah'] }}</td></tr>
        @endif
    </table>

    <div class="judul" style="font-size: 9.5pt; letter-spacing: 1pt; text-align: left; margin: 14pt 0 6pt;">
        2. Identitas Kualifikasi <span style="color: #6E7078;">/ Qualification</span>
    </div>

    <table class="biodata">
        <tr><td class="label">Program Studi <em>/ Study Programme</em></td><td class="pemisah">:</td><td>{{ $isi['prodi'] }}</td></tr>
        <tr>
            <td class="label">Jenjang <em>/ Level</em></td><td class="pemisah">:</td>
            <td>{{ $isi['jenjang'] }} <em>/ {{ $isi['jenjang_en'] }}</em></td>
        </tr>
        @if (!empty($isi['gelar']))
            <tr><td class="label">Gelar <em>/ Title Conferred</em></td><td class="pemisah">:</td><td>{{ $isi['gelar'] }} ({{ $isi['gelar_pendek'] }})</td></tr>
        @endif
        {{-- Baris yang paling dipakai pembaca asing: inilah yang memetakan
             kualifikasi Indonesia ke kerangka mereka sendiri. --}}
        <tr>
            <td class="label">Jenjang KKNI <em>/ IQF Level</em></td><td class="pemisah">:</td>
            <td><strong>Level {{ $isi['kkni'] }}</strong> <em>(Indonesian Qualification Framework)</em></td>
        </tr>
        <tr><td class="label">Bahasa Pengantar <em>/ Language of Instruction</em></td><td class="pemisah">:</td><td>{{ $isi['bahasa_pengantar'] }}</td></tr>
        <tr><td class="label">Beban Studi <em>/ Credits</em></td><td class="pemisah">:</td><td>{{ $isi['total_sks'] }} SKS</td></tr>
        <tr><td class="label">IPK <em>/ GPA</em></td><td class="pemisah">:</td><td>{{ Format::angka($isi['ipk']) }} — {{ $isi['predikat'] }}</td></tr>
    </table>

    <div class="judul" style="font-size: 9.5pt; letter-spacing: 1pt; text-align: left; margin: 14pt 0 6pt;">
        3. Capaian Pembelajaran <span style="color: #6E7078;">/ Learning Outcomes</span>
    </div>

    @if ($isi['cpl_kosong'] ?? true)
        {{-- Dinyatakan, bukan dibiarkan kosong. Bagian yang hilang tanpa
             keterangan terbaca sebagai kelalaian pemegang ijazahnya. --}}
        <p class="paragraf" style="font-size: 9pt; color: #6E7078;">
            Capaian pembelajaran program studi belum dicatatkan pada sistem akademik
            penerbit. <em>Programme learning outcomes have not been recorded by the
            issuing institution.</em>
        </p>
    @else
        @foreach (collect($isi['cpl'])->groupBy('kategori_label') as $kategori => $daftar)
            <div style="font-size: 8.5pt; font-weight: bold; color: #1E2761; margin-top: 8pt;">{{ $kategori }}</div>
            <table class="tabel-isi">
                @foreach ($daftar as $c)
                    <tr>
                        <td style="width: 12%;">{{ $c['kode'] }}</td>
                        <td>
                            {{ $c['deskripsi'] }}
                            @if (!empty($c['deskripsi_en']))
                                <div style="color: #6E7078; font-style: italic; margin-top: 1pt;">{{ $c['deskripsi_en'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @endforeach
    @endif

    @if (!empty($isi['aktivitas']))
        <div class="judul" style="font-size: 9.5pt; letter-spacing: 1pt; text-align: left; margin: 14pt 0 6pt;">
            4. Aktivitas dan Prestasi <span style="color: #6E7078;">/ Activities and Achievements</span>
        </div>

        <table class="tabel-isi">
            <thead>
                <tr>
                    <th style="width: 26%;">Jenis <em>/ Type</em></th>
                    <th>Kegiatan <em>/ Activity</em></th>
                    <th style="width: 22%;">Periode</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($isi['aktivitas'] as $a)
                    <tr>
                        <td>{{ $a['jenis'] }}</td>
                        <td>
                            {{ $a['judul'] }}
                            @if (!empty($a['penyelenggara']))
                                <div style="color: #6E7078;">{{ $a['penyelenggara'] }}</div>
                            @endif
                        </td>
                        <td>{{ Format::tanggal($a['mulai']) }}@if (!empty($a['selesai'])) – {{ Format::tanggal($a['selesai']) }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Hanya aktivitas terverifikasi yang muncul. Klaim yang belum
             diverifikasi adalah perkataan mahasiswa; mencetaknya di atas kop
             kampus mengubahnya menjadi perkataan kampus. --}}
        <p class="catatan-kecil">
            Hanya aktivitas yang telah diverifikasi program studi yang dicantumkan.
        </p>
    @endif

    @if (!empty($isi['judul_tugas_akhir']))
        <div class="judul" style="font-size: 9.5pt; letter-spacing: 1pt; text-align: left; margin: 14pt 0 6pt;">
            5. Tugas Akhir <span style="color: #6E7078;">/ Final Project</span>
        </div>
        <p class="paragraf" style="font-size: 9.5pt;">{{ $isi['judul_tugas_akhir'] }}</p>
    @endif
@endsection
