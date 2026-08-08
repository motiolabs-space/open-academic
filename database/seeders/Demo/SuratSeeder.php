<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\JenisSurat;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\ProdiCpl;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Surat\SuratService;
use Illuminate\Database\Seeder;

/**
 * Programme learning outcomes, and a handful of issued letters.
 *
 * The outcomes come first and matter most: without them every diploma
 * supplement on the demo campus prints its central section as "not recorded",
 * which is honest but shows nothing of what the document is for.
 *
 * Letters are created through SuratService rather than written straight to the
 * table, unlike the other demo seeders. The rules here — status checks, the
 * numbering sequence, the frozen snapshot — are cheap to satisfy with real data,
 * and going around them would produce demo letters that verify differently from
 * real ones.
 */
class SuratSeeder extends Seeder
{
    public function run(): void
    {
        $this->capaianPembelajaran();
        $this->contohSurat();
    }

    /**
     * A realistic outcome set for every programme.
     *
     * Four categories, as the national framework requires. The English half is
     * filled in because on a real campus it is the half that never gets done,
     * and a demo that quietly omits it teaches the wrong lesson.
     */
    private function capaianPembelajaran(): void
    {
        $template = [
            ['sikap', 'CPL-01',
                'Menjunjung tinggi nilai kemanusiaan dalam menjalankan tugas berdasarkan agama, moral, dan etika.',
                'Upholds human values in carrying out duties based on religion, morals, and ethics.'],
            ['sikap', 'CPL-02',
                'Menunjukkan sikap bertanggung jawab atas pekerjaan di bidang keahliannya secara mandiri.',
                'Demonstrates responsibility for work in their field of expertise independently.'],
            ['pengetahuan', 'CPL-03',
                'Menguasai konsep teoretis bidang pengetahuan tertentu secara umum dan mendalam.',
                'Masters general and in-depth theoretical concepts in a specific field of knowledge.'],
            ['pengetahuan', 'CPL-04',
                'Menguasai prinsip dan teknik perancangan sistem yang relevan dengan bidang keahliannya.',
                'Masters the principles and techniques of system design relevant to their field.'],
            ['keterampilan_umum', 'CPL-05',
                'Mampu menerapkan pemikiran logis, kritis, sistematis, dan inovatif dalam pengembangan ilmu pengetahuan.',
                'Able to apply logical, critical, systematic, and innovative thinking in developing knowledge.'],
            ['keterampilan_umum', 'CPL-06',
                'Mampu mengomunikasikan gagasan secara lisan dan tertulis kepada khalayak akademik maupun umum.',
                'Able to communicate ideas orally and in writing to academic and general audiences.'],
            ['keterampilan_khusus', 'CPL-07',
                'Mampu merancang dan mengimplementasikan solusi berbasis teknologi untuk permasalahan nyata.',
                'Able to design and implement technology-based solutions to real-world problems.'],
            ['keterampilan_khusus', 'CPL-08',
                'Mampu melakukan pengujian dan evaluasi terhadap solusi yang dibangun secara sistematis.',
                'Able to systematically test and evaluate the solutions they build.'],
        ];

        foreach (Prodi::all() as $prodi) {
            foreach ($template as $urutan => [$kategori, $kode, $deskripsi, $inggris]) {
                ProdiCpl::updateOrCreate(
                    ['prodi_id' => $prodi->id, 'kode' => $kode],
                    [
                        'kategori' => $kategori,
                        'deskripsi' => $deskripsi,
                        'deskripsi_en' => $inggris,
                        'urutan' => $urutan,
                    ],
                );
            }
        }
    }

    /**
     * Letters in every state a screen needs to show: issued, waiting, rejected,
     * and revoked.
     *
     * A queue where everything is already decided demonstrates nothing about the
     * screen that exists to decide things.
     */
    private function contohSurat(): void
    {
        $surat = app(SuratService::class);
        $staf = Staff::where('email', 'baak@demo.test')->first();

        $aktif = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->orderBy('id')
            ->limit(4)
            ->get();

        if ($aktif->count() < 4 || $staf === null) {
            return;
        }

        // Self-service: issued the moment it was asked for.
        $surat->ajukan($aktif[0], JenisSurat::AktifKuliah);

        // Waiting for a decision.
        $surat->ajukan($aktif[1], JenisSurat::Pengantar, 'Pengambilan data penelitian tugas akhir.');

        // Rejected, with a reason the applicant can act on.
        $ditolak = $surat->ajukan($aktif[2], JenisSurat::Pengantar, 'Keperluan pribadi.');
        $surat->tolak($ditolak, $staf, 'Keperluan belum menyebutkan instansi tujuan dan rentang waktunya.');

        // Issued and then withdrawn — still verifiable, as revoked.
        $dicabut = $surat->ajukan($aktif[3], JenisSurat::AktifKuliah);
        $surat->cabut($dicabut, $staf, 'Diterbitkan atas data semester yang keliru.');

        /*
         * A supplement for every graduate, not a sample.
         *
         * Regulation says every graduate receives one, so a demo campus where
         * some do not would read as a defect rather than as a demonstration.
         * KelulusanSeeder writes graduations directly to the table and therefore
         * never triggers the automatic issue in YudisiumService — this is the
         * backfill that produces the state a real campus would be in.
         */
        Mahasiswa::query()
            ->where('status', StudentStatus::Lulus->value)
            ->whereHas('yudisium', fn ($q) => $q->where('status', 'ditetapkan'))
            ->orderBy('id')
            ->get()
            ->each(fn (Mahasiswa $m) => $surat->terbitkanSkpi($m, $staf));
    }
}
