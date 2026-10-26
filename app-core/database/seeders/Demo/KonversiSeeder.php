<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Kemahasiswaan\AsalPendidikanData;
use App\DTOs\Kemahasiswaan\BarisTranskripData;
use App\DTOs\Kemahasiswaan\IdentitasMahasiswaData;
use App\Enums\Gender;
use App\Enums\JenisDaftar;
use App\Enums\JenisKonversi;
use App\Enums\StudentStatus;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Akademik\KonversiService;
use App\Services\Kemahasiswaan\PindahanService;
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
            ->limit(1)
            ->get();

        if ($calon->isEmpty()) {
            return;
        }

        $this->pindahan($konversi, $staf);
        $this->rpl($konversi, $staf, $calon[0]);
    }

    /**
     * Someone arriving from another campus with a transcript.
     *
     * Enrolled through the transfer intake itself — the registrar's direct
     * door — so the demo student carries what a real one would: an origin,
     * a NIM, an intake invoice, and recognised credits that are the sum of
     * approved conversions rather than a number the seeder made up.
     */
    private function pindahan(KonversiService $konversi, Staff $staf): void
    {
        $term = TahunAkademik::aktif();
        $prodi = Prodi::query()->orderBy('id')->first();

        if ($term === null || $prodi === null) {
            return;
        }

        $mahasiswa = app(PindahanService::class)->daftarkanLangsung(
            IdentitasMahasiswaData::fromArray([
                'nama' => 'Nadia Paramitha',
                'email' => 'nadia.pindahan@demo.test',
                'nik' => '3273015507040002',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2004-07-15',
                'jenis_kelamin' => Gender::Perempuan->value,
                'nama_ibu' => 'Sri Rahayu',
                'telepon' => '081234560077',
            ]),
            $prodi,
            $term,
            AsalPendidikanData::fromArray([
                'jenis_daftar' => JenisDaftar::Pindahan->value,
                'pt_asal' => 'Universitas Merdeka Nusantara',
                'prodi_asal' => 'Teknik Informatika',
                'term_masuk_asal' => ((int) $term->tahun_mulai - 2).'1',
            ]),
        )['mahasiswa'];

        // Same-named courses at the origin: a transfer between two campuses'
        // versions of one programme, which is the ordinary case.
        $mk = $this->mataKuliah($mahasiswa, 4)->values();
        $huruf = ['A', 'AB', 'B', 'B'];

        $usulan = app(PindahanService::class)->ajukanTranskrip($mahasiswa, $mk->map(
            fn (MataKuliah $satu, int $i): BarisTranskripData => new BarisTranskripData(
                $satu->id,
                $satu->nama,
                (int) $satu->sks,
                $huruf[$i],
            ),
        )->all());

        // The last one stays pending, so the decision screen has work on it.
        foreach ($usulan->slice(0, -1) as $i => $satu) {
            $konversi->setujui($satu, $staf, null, $huruf[$i]);
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
