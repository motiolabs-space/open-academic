{{--
    Kartu Tanda Mahasiswa, dicetak dua sisi pada satu lembar A4 untuk dipotong.

    Yang tercetak hanya NIM, nama, dan program studi. Tidak ada NIK, alamat
    rumah, maupun nama orang tua — kartu adalah dokumen yang paling sering
    hilang, dan segala yang tercetak padanya ikut tersebar bersamanya.
--}}
@extends('pdf.cetak.layout')

@push('gaya')
    <style>
        .kartu {
            width: 324pt; height: 204pt;    /* ±ID-1, 85.6 × 54 mm */
            border: 1pt solid #1E2761;
            padding: 0;
            margin-bottom: 14pt;
        }
        .kartu-kop {
            background: #1E2761; color: #FFFFFF;
            padding: 6pt 10pt; font-size: 9pt; font-weight: bold;
            letter-spacing: 0.6pt;
        }
        .kartu-isi td { padding: 8pt 10pt; vertical-align: top; }

        .pasfoto {
            width: 68pt; height: 90pt;
            border: 0.8pt dashed #A9A79E;
            text-align: center; color: #A9A79E; font-size: 6.5pt;
        }
        .pasfoto-teks { padding-top: 40pt; }

        .kartu-nim { font-size: 13pt; font-weight: bold; color: #1E2761; letter-spacing: 1pt; }
        .kartu-nama { font-size: 10.5pt; font-weight: bold; margin-top: 4pt; }
        .kartu-baris { font-size: 8pt; color: #6E7078; margin-top: 2pt; }

        .gunting { font-size: 7pt; color: #A9A79E; margin-bottom: 4pt; }
    </style>
@endpush

@section('isi')

    <div class="gunting">Potong mengikuti garis. Tempelkan pas foto 3×4 pada kotak yang disediakan.</div>

    {{-- Sisi depan --}}
    <table class="kartu">
        <tr>
            <td class="kartu-kop" colspan="2">{{ $dok['institusi_singkat'] }} · KARTU TANDA MAHASISWA</td>
        </tr>
        <tr class="kartu-isi">
            <td style="width: 78pt">
                <div class="pasfoto"><div class="pasfoto-teks">PAS FOTO<br>3 × 4</div></div>
            </td>
            <td>
                <div class="kartu-nim">{{ $mahasiswa->nim }}</div>
                <div class="kartu-nama">{{ $mahasiswa->nama }}</div>
                <div class="kartu-baris">{{ $mahasiswa->prodi?->nama }}</div>
                <div class="kartu-baris">{{ $mahasiswa->prodi?->fakultas?->nama }}</div>
                <div class="kartu-baris">Angkatan {{ $mahasiswa->angkatan }}</div>
            </td>
            <td style="width: 62pt" class="tengah">
                @if ($qr)
                    {{-- Isinya NIM, yang sudah tercetak di sebelahnya. --}}
                    <img src="{{ $qr }}" alt="" style="width: 54pt; height: 54pt;">
                @endif
            </td>
        </tr>
    </table>

    {{-- Sisi belakang --}}
    <table class="kartu">
        <tr>
            <td class="kartu-kop">{{ $dok['institusi'] }}</td>
        </tr>
        <tr class="kartu-isi">
            <td>
                @if ($dok['alamat'])
                    <div class="kartu-baris">{{ $dok['alamat'] }}</div>
                @endif
                @if ($dok['kontak'])
                    <div class="kartu-baris">{{ $dok['kontak'] }}</div>
                @endif

                <div class="kartu-baris" style="margin-top: 10pt; line-height: 1.5;">
                    {{ $dok['catatan_kaki'] }}
                </div>

                @if ($dok['bertanda_tangan'] && $dok['penandatangan'] && $dok['penandatangan']['nama'] !== '')
                    <div class="kartu-baris" style="margin-top: 14pt;">
                        {{ $dok['penandatangan']['jabatan'] }}<br>
                        <strong>{{ $dok['penandatangan']['nama'] }}</strong>
                    </div>
                @endif
            </td>
        </tr>
    </table>

@endsection
