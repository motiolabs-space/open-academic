<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Sdm\RiwayatKepegawaianData;
use App\Enums\JenisRiwayat;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Sdm\RiwayatKepegawaianService;
use Illuminate\Database\Seeder;

/**
 * One complete personnel file, and a review queue with work in it.
 *
 * The first lecturer gets a row of every history a lecturer can have — the
 * shape SISTER's "Profil Dosen" asks for, filled. Retirement is the one
 * exception: on that lecturer it would close the demo's busiest account, so it
 * goes to the last lecturer instead, dated next month, where the daily closure
 * has something to do.
 *
 * Three entries are left waiting and one is rejected, so the validation screen
 * and the lecturer's "returned with a reason" state both have something to show.
 *
 * After MutuSeeder, which plants the unit tree that postings and structural
 * posts point at.
 */
class RiwayatKepegawaianSeeder extends Seeder
{
    public function run(): void
    {
        $dosen = Dosen::whereNotNull('nidn')->orderBy('id')->get();
        $staf = Staff::where('email', 'admin@demo.test')->first() ?? Staff::orderBy('id')->first();

        if ($dosen->count() < 3 || $staf === null) {
            return;
        }

        $riwayat = app(RiwayatKepegawaianService::class);
        $unit = UnitKerja::where('is_active', true)->orderBy('id')->pluck('id', 'kode');
        $utama = $dosen->first();

        $catat = fn (Dosen $d, JenisRiwayat $jenis, array $isian) => $riwayat->catat(
            $d, $staf, RiwayatKepegawaianData::fromArray($jenis, $isian),
        );

        $catat($utama, JenisRiwayat::Pangkat, ['golongan' => 'III/b', 'tmt' => '2016-04-01', 'nomor_sk' => 'SK/PKT/2016/014']);
        $catat($utama, JenisRiwayat::Pangkat, ['golongan' => 'III/c', 'tmt' => '2020-04-01', 'nomor_sk' => 'SK/PKT/2020/031']);
        $catat($utama, JenisRiwayat::Inpassing, [
            'golongan' => 'III/b', 'angka_kredit' => 150, 'nomor_sk' => 'INP/2015/0077', 'tmt' => '2015-10-01',
        ]);
        $catat($utama, JenisRiwayat::JabatanStruktural, [
            'nama_jabatan' => 'Sekretaris Lembaga Penjaminan Mutu',
            'unit_kerja_id' => $unit->get('LPM'),
            'nomor_sk' => 'SK/REK/2024/112', 'tmt' => '2024-02-01',
        ]);
        $catat($utama, JenisRiwayat::TugasBelajar, [
            'jenis' => 'tugas_belajar', 'jenjang' => '40', 'perguruan_tinggi' => 'National University of Singapore',
            'program_studi' => 'Computer Science', 'negara' => 'Singapura', 'sumber_biaya' => 'Beasiswa LPDP',
            'nomor_sk' => 'SK/TB/2015/009', 'tanggal_mulai' => '2015-08-01', 'tanggal_selesai' => '2019-07-31',
        ]);
        $catat($utama, JenisRiwayat::Diklat, [
            'nama' => 'Pelatihan Keterampilan Dasar Teknik Instruksional (PEKERTI)', 'jenis' => 'fungsional',
            'penyelenggara' => 'LLDIKTI', 'tingkat' => 'nasional',
            'tanggal_mulai' => '2012-03-05', 'tanggal_selesai' => '2012-03-10', 'jumlah_jam' => 50,
        ]);
        $catat($utama, JenisRiwayat::Diklat, [
            'nama' => 'Applied Approach (AA)', 'jenis' => 'fungsional', 'penyelenggara' => 'Universitas Gadjah Mada',
            'tingkat' => 'nasional', 'tanggal_mulai' => '2013-07-01', 'tanggal_selesai' => '2013-07-12', 'jumlah_jam' => 90,
        ]);
        $catat($utama, JenisRiwayat::PengalamanKerja, [
            'instansi' => 'PT Data Nusantara', 'jabatan' => 'Software Engineer', 'bidang' => 'Teknologi informasi',
            'tanggal_mulai' => '2008-09-01', 'tanggal_selesai' => '2011-08-31',
        ]);
        $catat($utama, JenisRiwayat::TugasDinas, [
            'tujuan' => 'Jakarta', 'instansi_tujuan' => 'Direktorat Jenderal Pendidikan Tinggi',
            'keperluan' => 'Rapat koordinasi pelaporan PDDIKTI', 'nomor_surat_tugas' => 'ST/2026/0412',
            'tanggal_mulai' => now()->subMonths(2)->toDateString(), 'tanggal_selesai' => now()->subMonths(2)->addDays(2)->toDateString(),
        ]);
        $catat($utama, JenisRiwayat::PenghargaanSanksi, [
            'jenis' => 'penghargaan', 'nama' => 'Satyalancana Karya Satya X Tahun', 'tingkat' => 'nasional',
            'pemberi' => 'Presiden Republik Indonesia', 'tanggal' => '2022-08-17', 'nomor_sk' => 'KEPPRES/2022/045',
        ]);
        $catat($utama, JenisRiwayat::Organisasi, [
            'nama' => 'Asosiasi Perguruan Tinggi Informatika dan Komputer', 'peran' => 'Anggota',
            'tingkat' => 'nasional', 'tahun_mulai' => 2014,
        ]);
        $catat($utama, JenisRiwayat::Bahasa, [
            'bahasa' => 'Inggris', 'kemampuan' => 'mahir', 'sertifikat' => 'TOEFL ITP', 'skor' => 590,
        ]);
        $catat($utama, JenisRiwayat::Keluarga, [
            'nama' => 'Ratna Kusumawati', 'hubungan' => 'pasangan', 'tanggal_lahir' => '1988-05-14',
            'pekerjaan' => 'Guru', 'is_tanggungan' => true,
        ]);
        $catat($utama, JenisRiwayat::Kontrak, [
            'nomor_kontrak' => 'PK/YYS/2011/021', 'jenis' => 'Perjanjian kerja dosen tetap yayasan',
            'tanggal_mulai' => '2011-09-01', 'tanggal_selesai' => '2013-08-31',
        ]);

        if ($unit->has('BAAK') && $unit->has('LPM')) {
            $catat($utama, JenisRiwayat::Mutasi, ['jenis' => 'masuk', 'unit_tujuan_id' => $unit->get('BAAK'), 'tmt' => '2011-09-01']);
            $catat($utama, JenisRiwayat::Mutasi, ['jenis' => 'pindah', 'unit_tujuan_id' => $unit->get('LPM'), 'tmt' => '2024-02-01']);
        }

        // Retirement on a different lecturer, a month out — see the class note.
        $catat($dosen->last(), JenisRiwayat::Pensiun, [
            'jenis' => 'batas_usia', 'tmt' => now()->addMonth()->startOfMonth()->toDateString(),
            'nomor_sk' => 'SK/YYS/PNS/2026/003',
        ]);

        // The queue: three waiting, one returned with a reason.
        $ajukan = fn (Dosen $d, JenisRiwayat $jenis, array $isian) => $riwayat->ajukan(
            $d, RiwayatKepegawaianData::fromArray($jenis, $isian),
        );

        $ajukan($dosen[1], JenisRiwayat::Diklat, [
            'nama' => 'Pelatihan Penulisan Artikel Jurnal Bereputasi', 'jenis' => 'teknis',
            'penyelenggara' => 'LPPM', 'tanggal_mulai' => now()->subWeeks(3)->toDateString(), 'jumlah_jam' => 16,
        ]);
        $ajukan($dosen[1], JenisRiwayat::Bahasa, ['bahasa' => 'Inggris', 'kemampuan' => 'menengah', 'skor' => 520]);
        $ajukan($dosen[2], JenisRiwayat::Pangkat, ['golongan' => 'III/b', 'tmt' => '2023-04-01', 'nomor_sk' => 'SK/PKT/2023/088']);

        $ditolak = $ajukan($dosen[2], JenisRiwayat::Organisasi, ['nama' => 'Ikatan Ahli Informatika Indonesia', 'tahun_mulai' => 2018]);
        $riwayat->tolak($ditolak, $staf, 'Lampirkan kartu anggota atau surat keanggotaan yang masih berlaku.');
    }
}
