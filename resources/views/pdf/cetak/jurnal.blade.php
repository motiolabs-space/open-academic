{{--
    Jurnal perkuliahan (BAP): apa yang benar-benar diajarkan, pertemuan demi
    pertemuan.

    Pertemuan yang belum berjurnal dicetak kosong, bukan disembunyikan.
    Jaraknya justru intinya — jurnal dengan empat dari empat belas pertemuan
    terisi adalah temuan yang dicari monitoring, dan lembar yang menyembunyikan
    baris kosongnya ikut menyembunyikan temuan itu.
--}}
@extends('pdf.cetak.layout')

@push('gaya')
    <style>
        .daftar td, .daftar th { font-size: 7.5pt; padding: 4pt 5pt; }
        .kolom-ke { width: 26pt; }
        .kolom-tanggal { width: 62pt; }
        .kolom-hadir { width: 44pt; }
        .kolom-ttd { width: 90pt; }
        .baris-jurnal td { height: 26pt; vertical-align: top; }
        .kosong { color: #A9A79E; }
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
            <td colspan="4">{{ $kelas->dosen->pluck('nama')->implode(', ') ?: '—' }}</td>
        </tr>
    </table>

    @php
        // Dikunci per nomor pertemuan supaya baris yang belum ada tetap tercetak
        // kosong alih-alih hilang dari daftar.
        $terisi = $pertemuan->keyBy('pertemuan_ke');
    @endphp

    <table class="daftar">
        <thead>
            <tr>
                <th class="kolom-ke tengah">Ke</th>
                <th class="kolom-tanggal">Tanggal</th>
                <th>Materi yang Diajarkan</th>
                <th class="kolom-hadir tengah">Hadir</th>
                <th class="kolom-ttd">Paraf Dosen</th>
            </tr>
        </thead>
        <tbody>
            @for ($ke = 1; $ke <= $jumlahPertemuan; $ke++)
                @php $p = $terisi->get($ke); @endphp
                <tr class="baris-jurnal">
                    <td class="tengah tabular">{{ $ke }}</td>
                    <td class="tabular">
                        {{ $p?->tanggal?->translatedFormat('d/m/Y') ?? '' }}
                    </td>
                    <td>
                        @if ($p?->materi)
                            {{ $p->materi }}
                        @else
                            <span class="kosong">—</span>
                        @endif
                    </td>
                    <td class="tengah tabular">
                        @if ($p?->jurnal_diisi_at)
                            {{ $p->jumlah_hadir }}/{{ $p->jumlah_peserta }}
                        @endif
                    </td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    @php
        $berjurnal = $pertemuan->whereNotNull('jurnal_diisi_at')->count();
        $terlaksana = $pertemuan->where('is_terlaksana', true)->count();
    @endphp

    <div style="margin-top: 8pt; font-size: 7.5pt; color: #6E7078;">
        {{-- Dua angka, bukan satu. Empat belas terlaksana dengan empat berjurnal
             bukan berarti kurang mengajar, melainkan kurang mendokumentasikan —
             dan satu angka gabungan menyembunyikan yang mana dari keduanya. --}}
        Terlaksana: <strong>{{ $terlaksana }}</strong> dari {{ $jumlahPertemuan }} ·
        Berjurnal: <strong>{{ $berjurnal }}</strong> dari {{ $jumlahPertemuan }}
    </div>

@endsection
