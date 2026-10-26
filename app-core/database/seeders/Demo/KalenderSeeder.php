<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Akademik\PeristiwaKalenderData;
use App\Enums\JenisPeristiwa;
use App\Enums\SasaranPeristiwa;
use App\Enums\StatusPeristiwa;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Services\Akademik\KalenderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Holidays, campus activities and deadlines across the active term.
 *
 * Everything else on the demo calendar — KRS, grading, the published UTS,
 * EDOM, graduation, admission — comes from the seeders that own those dates.
 *
 * Dates are anchored to the term's start or to today, like the term itself is
 * anchored to today, so the calendar always has something behind, on, and
 * ahead of the day it is opened. The
 * holidays stay clear of the UTS fortnight JadwalUjianSeeder lays out from week
 * eight: the demo timetable is meant to publish without warnings, and a holiday
 * warning on it would be the demo contradicting itself. One holiday is left as
 * a draft so the difference between draft and published can be shown.
 */
class KalenderSeeder extends Seeder
{
    public function run(KalenderService $kalender): void
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            return;
        }

        $mulai = $term->tanggal_mulai->copy()->startOfWeek();
        $hariIni = Carbon::today();
        $informatika = Prodi::query()->where('nama', 'Informatika')->value('id');

        $peristiwa = [
            [JenisPeristiwa::Libur, 'Libur Nasional', $mulai->copy()->addWeeks(3)->addDays(3), null, SasaranPeristiwa::Semua, null, StatusPeristiwa::Terbit, 'Perkuliahan dan layanan akademik ditiadakan.'],
            [JenisPeristiwa::Kegiatan, 'Pekan Orientasi Program Studi', $mulai->copy()->addDays(1), $mulai->copy()->addDays(3), SasaranPeristiwa::Mahasiswa, $informatika, StatusPeristiwa::Terbit, 'Wajib bagi mahasiswa baru Informatika.'],
            // Mengapit hari ini, supaya bulan yang dibuka pertama kali tidak
            // hanya berisi tanggal turunan.
            [JenisPeristiwa::Kegiatan, 'Sosialisasi Program MBKM', $hariIni->copy()->subDay(), $hariIni->copy()->addDay(), SasaranPeristiwa::Mahasiswa, null, StatusPeristiwa::Terbit, 'Aula utama, 09.00 WIB.'],
            [JenisPeristiwa::Kegiatan, 'Rapat Kerja Bagian Akademik', $hariIni->copy()->addDays(7), null, SasaranPeristiwa::Staf, null, StatusPeristiwa::Terbit, null],
            [JenisPeristiwa::Batas, 'Batas Pengajuan Cuti Akademik', $hariIni->copy()->addDays(10), null, SasaranPeristiwa::Mahasiswa, null, StatusPeristiwa::Terbit, 'Pengajuan sesudah tanggal ini berlaku untuk semester berikutnya.'],
            [JenisPeristiwa::Batas, 'Batas Unggah RPS dan Rencana Evaluasi', $hariIni->copy()->addDays(14), null, SasaranPeristiwa::Dosen, null, StatusPeristiwa::Terbit, null],
            [JenisPeristiwa::Libur, 'Cuti Bersama', $mulai->copy()->addWeeks(11), $mulai->copy()->addWeeks(11)->addDay(), SasaranPeristiwa::Semua, null, StatusPeristiwa::Terbit, null],
            [JenisPeristiwa::Kegiatan, 'Dies Natalis Kampus', $mulai->copy()->addWeeks(13)->addDays(2), null, SasaranPeristiwa::Semua, null, StatusPeristiwa::Terbit, 'Upacara dan rangkaian acara; perkuliahan pagi ditiadakan.'],
            [JenisPeristiwa::Libur, 'Libur Akhir Tahun', $mulai->copy()->addWeeks(16), $mulai->copy()->addWeeks(16)->addDays(4), SasaranPeristiwa::Semua, null, StatusPeristiwa::Draf, 'Menunggu surat keputusan rektor.'],
        ];

        foreach ($peristiwa as [$jenis, $judul, $dari, $sampai, $sasaran, $prodiId, $status, $keterangan]) {
            $kalender->simpan(new PeristiwaKalenderData(
                judul: $judul,
                jenis: $jenis,
                tanggalMulai: $dari->toDateString(),
                tanggalSelesai: ($sampai ?? $dari)->toDateString(),
                sasaran: $sasaran,
                prodiId: $prodiId,
                status: $status,
                keterangan: $keterangan,
            ));
        }
    }
}
