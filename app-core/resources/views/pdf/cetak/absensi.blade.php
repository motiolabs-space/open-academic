{{--
    Daftar hadir kuliah — lembar kertas yang diedarkan di ruangan.

    Kolom tanda tangan sengaja dibiarkan kosong. Inilah satu-satunya dokumen
    di sistem ini yang justru **tidak boleh** diisi dari basis data: tanda
    tangan basah adalah buktinya, dan mencetak presensi digital ke dalamnya
    berarti mengganti bukti dengan salinan dari apa yang sistem sudah percayai.
--}}
@extends('pdf.cetak.layout')

@push('gaya')
    <style>
        .daftar td, .daftar th { font-size: 7.5pt; padding: 3pt 4pt; }
        .kolom-nim { width: 62pt; }
        .kolom-nama { width: 150pt; }
        .kolom-hadir { width: 26pt; }
        .baris-tinggi td { height: 16pt; }
    </style>
@endpush

@section('isi')

    <table class="meta" style="margin-bottom: 8pt;">
        <tr>
            <td class="label" style="width: 12%">Kelas</td><td class="pemisah">:</td>
            <td>{{ $kelas->namaLengkap() }} · {{ $kelas->sks }} SKS</td>

            <td class="label" style="width: 12%">Semester</td><td class="pemisah">:</td>
            <td>{{ $kelas->tahunAkademik?->nama }}</td>
        </tr>
        <tr>
            <td class="label">Dosen</td><td class="pemisah">:</td>
            <td>{{ $kelas->dosen->pluck('nama')->implode(', ') ?: '—' }}</td>

            <td class="label">Jadwal</td><td class="pemisah">:</td>
            <td>{{ $kelas->jadwal->map(fn ($j) => $j->rentangWaktu())->implode(' · ') ?: '—' }}</td>
        </tr>
    </table>

    <table class="daftar">
        <thead>
            <tr>
                <th class="nomor">No</th>
                <th class="kolom-nim">NIM</th>
                <th class="kolom-nama">Nama</th>
                @for ($i = 1; $i <= $jumlahPertemuan; $i++)
                    <th class="kolom-hadir tengah">{{ $i }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @forelse ($peserta as $i => $mahasiswa)
                <tr class="baris-tinggi">
                    <td class="nomor">{{ $i + 1 }}</td>
                    <td class="tabular">{{ $mahasiswa->nim }}</td>
                    <td>{{ $mahasiswa->nama }}</td>
                    @for ($p = 1; $p <= $jumlahPertemuan; $p++)
                        <td></td>
                    @endfor
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 3 + $jumlahPertemuan }}" class="tengah">
                        Belum ada mahasiswa terdaftar pada kelas ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top: 10pt;">
        <tr>
            <td style="font-size: 7.5pt; color: #6E7078;">
                Jumlah peserta terdaftar: <strong>{{ $peserta->count() }}</strong>
            </td>
            <td style="font-size: 7.5pt; color: #6E7078; text-align: right;">
                Paraf dosen tiap pertemuan: ______________________
            </td>
        </tr>
    </table>

@endsection
