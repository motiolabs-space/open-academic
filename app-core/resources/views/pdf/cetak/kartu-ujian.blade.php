{{--
    Kartu ujian: satu baris per kelas yang diambil.

    Kelas yang tidak memenuhi syarat tetap dicetak dan ditandai, bukan
    dihilangkan. Kartu yang sekadar lebih pendek dari KRS-nya mengirim
    mahasiswa bertanya kepada pengawas di depan ruang ujian.

    Dua mode: bila $jenis terisi, baris berasal dari jadwal ujian yang terbit
    (tanggal, jam, dan ruang tempat mahasiswa ini duduk); bila tidak, dari
    jadwal kuliah mingguan seperti sebelumnya.
--}}
@extends('pdf.cetak.layout')

@push('gaya')
    <style>
        .identitas { margin-bottom: 10pt; }
        .identitas td { vertical-align: top; }
        .qr { width: 70pt; text-align: right; }
        .tidak-layak { color: #A02D2D; font-weight: bold; }
        .ttd-peserta { width: 130pt; }
    </style>
@endpush

@section('isi')

    <table class="identitas">
        <tr>
            <td>
                <table class="meta">
                    <tr>
                        <td class="label">NIM</td><td class="pemisah">:</td>
                        <td class="tabular"><strong>{{ $mahasiswa->nim }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Nama</td><td class="pemisah">:</td>
                        <td><strong>{{ $mahasiswa->nama }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Program Studi</td><td class="pemisah">:</td>
                        <td>{{ $mahasiswa->prodi?->nama }}</td>
                    </tr>
                    <tr>
                        <td class="label">Semester</td><td class="pemisah">:</td>
                        <td>{{ $krs->semester_ke }} · {{ $krs->total_sks }} SKS</td>
                    </tr>
                    @if ($jenis && $tampilkanKeuangan)
                        <tr>
                            <td class="label">{{ __('jadwal_ujian.kartu_ujian.status_keuangan') }}</td><td class="pemisah">:</td>
                            <td>{{ __('jadwal_ujian.keterangan.lunas') }}</td>
                        </tr>
                    @endif
                </table>
            </td>
            <td class="qr">
                @if ($qr)
                    <img src="{{ $qr }}" alt="" style="width: 60pt; height: 60pt;">
                @endif
            </td>
        </tr>
    </table>

    <table class="daftar">
        <thead>
            <tr>
                <th class="nomor">No</th>
                <th style="width: 62pt">Kode</th>
                <th>Mata Kuliah</th>
                <th style="width: 34pt" class="tengah">SKS</th>
                <th style="width: 92pt">{{ $jenis ? __('jadwal_ujian.kolom.waktu') : 'Jadwal' }}</th>
                <th style="width: 68pt">Ruang</th>
                <th style="width: 52pt" class="tengah">Hadir</th>
                <th class="ttd-peserta">Paraf Pengawas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $i => $b)
                <tr>
                    <td class="nomor">{{ $i + 1 }}</td>
                    <td class="tabular">{{ $b['kelas']->mataKuliah->kode }}</td>
                    <td>
                        {{ $b['kelas']->mataKuliah->nama }}
                        @unless ($b['layak'])
                            <span class="tidak-layak">{{ __('jadwal_ujian.kartu_ujian.tidak_layak_kehadiran') }}</span>
                        @endunless
                    </td>
                    <td class="tengah tabular">{{ $b['kelas']->sks }}</td>
                    @if ($jenis)
                        <td class="tabular">
                            @if ($b['ujian'])
                                {{ \App\Support\Format::tanggalHari($b['ujian']->tanggal) }}<br>
                                {{ \App\Support\Format::rentangJam($b['ujian']->jam_mulai, $b['ujian']->jam_selesai) }}
                            @else
                                {{ __('jadwal_ujian.kartu_ujian.belum_terbit') }}
                            @endif
                        </td>
                        <td>{{ $b['ruang']?->nama ?? '—' }}</td>
                    @else
                        <td class="tabular">{{ $b['jadwal']?->rentangWaktu() ?? '—' }}</td>
                        <td>{{ $b['jadwal']?->ruang?->nama ?? '—' }}</td>
                    @endif
                    <td class="tengah tabular">
                        {{ $b['persen'] === null ? '—' : number_format($b['persen'], 0).'%' }}
                    </td>
                    <td class="ttd-kolom"></td>
                </tr>
            @empty
                <tr><td colspan="8" class="tengah">Belum ada kelas pada rencana studi ini.</td></tr>
            @endforelse
        </tbody>
    </table>

@endsection
