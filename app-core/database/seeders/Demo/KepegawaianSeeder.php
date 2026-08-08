<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\EducationLevel;
use App\Enums\JabatanFungsional;
use App\Enums\JenisSertifikasi;
use App\Enums\KesimpulanBkd;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\RiwayatPendidikanDosen;
use App\Models\Sdm\SertifikasiDosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\BkdService;
use App\Services\Sdm\PortofolioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The lecturer-side record: degrees, ranks, certificates, and a reporting round
 * caught mid-flight.
 *
 * Three states are arranged deliberately, because a demo where every report is
 * signed shows none of the screens that matter:
 *
 *   - one report endorsed, so the finished shape is visible;
 *   - one submitted and waiting, so the assessor's queue has work in it;
 *   - one returned for correction, so the lecturer's sheet is editable again
 *     and carries an assessor's note.
 *
 * And two lecturers hold no Serdos, so the "not obliged to report" path is
 * exercised rather than assumed.
 */
class KepegawaianSeeder extends Seeder
{
    public function run(): void
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            return;
        }

        $dosen = Dosen::whereNotNull('nidn')->orderBy('id')->get();

        if ($dosen->count() < 4) {
            return;
        }

        $this->seedPortofolio($dosen);
        $this->seedLaporan($dosen, $term);
    }

    /** @param Collection<int, Dosen> $dosen */
    private function seedPortofolio($dosen): void
    {
        $portofolio = app(PortofolioService::class);

        $peta = [
            JabatanFungsional::LektorKepala->label() => JabatanFungsional::LektorKepala,
            JabatanFungsional::Lektor->label() => JabatanFungsional::Lektor,
            JabatanFungsional::AsistenAhli->label() => JabatanFungsional::AsistenAhli,
        ];

        foreach ($dosen as $index => $satu) {
            $doktor = $satu->pendidikan_tertinggi === EducationLevel::S3;

            // Bachelor's and master's for everybody, doctorate where the title
            // says so — the ladder the flat pendidikan_tertinggi column
            // summarises.
            $jenjang = [
                [EducationLevel::S1, 'Universitas Gadjah Mada', 'Teknik Informatika', 'S.Kom.', 2008 + $index],
                [EducationLevel::S2, 'Institut Teknologi Bandung', 'Informatika', 'M.Kom.', 2013 + $index],
            ];

            if ($doktor) {
                // A foreign degree in the mix, so the recognition-decree flag on
                // the screen has something to point at.
                $jenjang[] = [EducationLevel::S3, 'National University of Singapore', 'Computer Science', 'Ph.D.', 2019];
            }

            foreach ($jenjang as [$tingkat, $kampus, $prodi, $gelar, $tahun]) {
                RiwayatPendidikanDosen::create([
                    'dosen_id' => $satu->id,
                    'jenjang' => $tingkat,
                    'perguruan_tinggi' => $kampus,
                    'program_studi' => $prodi,
                    'bidang_ilmu' => $prodi,
                    'negara' => $tingkat === EducationLevel::S3 && $doktor ? 'Singapura' : 'Indonesia',
                    'tahun_masuk' => $tahun - 4,
                    'tahun_lulus' => $tahun,
                    'gelar' => $gelar,
                    'nomor_ijazah' => sprintf('IJZ/%d/%04d', $tahun, $satu->id),
                ]);
            }

            $jabatan = $peta[$satu->jabatan_fungsional] ?? JabatanFungsional::AsistenAhli;

            $portofolio->catatJabatan(
                $satu,
                $jabatan,
                now()->subYears(3)->toDateString(),
                angkaKredit: (float) $jabatan->angkaKreditMinimum() + 25,
                nomorSk: sprintf('SK/%d/KEPEG/%d', 2000 + $satu->id, now()->year - 3),
                tanggalSk: now()->subYears(3)->subMonth()->toDateString(),
            );

            /*
             * Two lecturers deliberately without Serdos.
             *
             * BKD is a condition of the certification allowance, so somebody who
             * does not hold one is not obliged to report — and a demo where
             * everybody is obliged never shows the screen saying so.
             */
            if ($index < $dosen->count() - 2) {
                SertifikasiDosen::create([
                    'dosen_id' => $satu->id,
                    'jenis' => JenisSertifikasi::Serdos,
                    'nama' => 'Sertifikat Pendidik untuk Dosen',
                    'nomor' => sprintf('%d%06d', now()->year - 4, $satu->id),
                    'penyelenggara' => 'Kementerian Pendidikan Tinggi',
                    'bidang' => $satu->prodi?->nama,
                    'tanggal' => now()->subYears(4)->toDateString(),
                ]);
            }

            if ($index % 3 === 0) {
                SertifikasiDosen::create([
                    'dosen_id' => $satu->id,
                    'jenis' => JenisSertifikasi::Kompetensi,
                    'nama' => 'Certified Data Analyst',
                    'nomor' => sprintf('BNSP/%04d', $satu->id),
                    'penyelenggara' => 'BNSP',
                    'tanggal' => now()->subYear()->toDateString(),

                    // Competence certificates expire; Serdos does not. Having one
                    // of each is what makes the expiry chip meaningful.
                    'berlaku_sampai' => now()->addYears(2)->toDateString(),
                ]);
            }
        }
    }

    /** @param Collection<int, Dosen> $dosen */
    private function seedLaporan($dosen, TahunAkademik $term): void
    {
        $bkd = app(BkdService::class);
        $staff = Staff::where('email', 'baak@demo.test')->first();

        $wajib = $dosen->filter(fn (Dosen $d): bool => $d->wajibBkd())->values();

        if ($wajib->count() < 3) {
            return;
        }

        // Assessors come from the pool itself, offset so nobody assesses their
        // own — the service refuses that anyway, and tripping it here would only
        // break the seed.
        $asesor = fn (int $i, int $geser): Dosen => $wajib[($i + $geser) % $wajib->count()];

        foreach ($wajib->take(3) as $i => $satu) {
            $laporan = $bkd->laporan($satu, $term);

            $bkd->ajukan($laporan);
            $bkd->tetapkanAsesor($laporan, $asesor($i, 1), $asesor($i, 2));

            match ($i) {
                0 => $this->disahkan($bkd, $laporan->fresh(), $asesor($i, 1), $staff),
                1 => null, // left waiting, so the assessor queue is not empty
                default => $bkd->kembalikan(
                    $laporan->fresh(),
                    $asesor($i, 1),
                    'Kegiatan penelitian belum dilampirkan buktinya. Mohon unggah surat tugas '
                        .'dan luaran, lalu ajukan ulang.',
                ),
            };
        }
    }

    private function disahkan(BkdService $bkd, $laporan, Dosen $asesor, ?Staff $staff): void
    {
        $bkd->nilai($laporan, $asesor, KesimpulanBkd::Memenuhi, 'Seluruh unsur terpenuhi.');

        if ($staff !== null) {
            $bkd->sahkan($laporan->fresh(), $staff);
        }
    }
}
