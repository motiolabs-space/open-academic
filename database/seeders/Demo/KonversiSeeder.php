<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\JenisKonversi;
use App\Enums\StudentStatus;
use App\Models\Akademik\MataKuliah;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Akademik\KonversiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * A transfer student and an RPL student, so the screens have something real.
 *
 * Built through KonversiService rather than written to the table, unlike most of
 * the demo seeders: every rule here is satisfiable with ordinary data, and going
 * around them would produce demo conversions that behave differently from the
 * ones a person creates.
 *
 * One proposal is deliberately left undecided. A queue where everything has
 * already been settled shows nothing about the screen that exists to settle it.
 */
class KonversiSeeder extends Seeder
{
    public function run(): void
    {
        $konversi = app(KonversiService::class);
        $staf = Staff::where('email', 'baak@demo.test')->first();

        if ($staf === null) {
            return;
        }

        /*
         * Students with no grades of their own.
         *
         * A conversion is refused for a course already studied here, so picking
         * somebody mid-degree would mostly produce refusals. The newest intake
         * is where transfer and RPL admissions actually land.
         */
        $calon = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereDoesntHave('nilai')
            ->orderByDesc('angkatan')
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($calon->count() < 2) {
            return;
        }

        $this->pindahan($konversi, $staf, $calon[0]);
        $this->rpl($konversi, $staf, $calon[1]);
    }

    /** Someone arriving from another campus with a transcript. */
    private function pindahan(KonversiService $konversi, Staff $staf, Mahasiswa $mahasiswa): void
    {
        $mk = $this->mataKuliah($mahasiswa, 4);

        $asal = [
            ['Algoritma dan Pemrograman', 'A'],
            ['Struktur Data', 'AB'],
            ['Basis Data', 'B'],
            ['Jaringan Komputer', 'B'],
        ];

        foreach ($mk as $i => $satu) {
            $usulan = $konversi->ajukan(
                $mahasiswa,
                $satu,
                JenisKonversi::Transfer,
                $asal[$i][0],
                'Universitas Merdeka Nusantara',
                (int) $satu->sks,
                $asal[$i][1],
            );

            // The last one stays pending, so the decision screen has work on it.
            if ($i < count($asal) - 1) {
                $konversi->setujui($usulan, $staf, (int) $satu->sks, $asal[$i][1]);
            }
        }
    }

    /**
     * Someone whose work experience was assessed as equivalent.
     *
     * Recorded without letter grades, which is ordinary for RPL: the campus
     * decided the requirement was met, not that it deserved a B.
     */
    private function rpl(KonversiService $konversi, Staff $staf, Mahasiswa $mahasiswa): void
    {
        $mk = $this->mataKuliah($mahasiswa, 2);

        $pengalaman = [
            'Enam tahun sebagai administrator basis data di PT Nusantara Digital Teknologi',
            'Empat tahun sebagai administrator jaringan, bersertifikat',
        ];

        foreach ($mk as $i => $satu) {
            $usulan = $konversi->ajukan(
                $mahasiswa,
                $satu,
                JenisKonversi::Rpl,
                $pengalaman[$i],
            );

            $konversi->setujui($usulan, $staf, (int) $satu->sks, null,
                'Asesmen portofolio dan wawancara oleh tim asesor program studi.');
        }
    }

    /** @return Collection<int, MataKuliah> */
    private function mataKuliah(Mahasiswa $mahasiswa, int $jumlah)
    {
        return MataKuliah::query()
            ->where('prodi_id', $mahasiswa->prodi_id)
            ->orderBy('kode')
            ->limit($jumlah)
            ->get();
    }
}
