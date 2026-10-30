<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KelasKuliah> */
class KelasKuliahFactory extends Factory
{
    protected $model = KelasKuliah::class;

    /**
     * Class codes handed out in this process.
     *
     * A random letter from A–C collided one time in three whenever a test
     * made two classes of one course in one term — `kelas_kuliah` is unique
     * on (term, course, code) — and the failure showed up on whichever PHP
     * build drew the same letter twice (PR #37 CI). A sequence never repeats,
     * and its `F` prefix cannot match a code a test sets itself ("A", "B",
     * "X"), so passing `kode` explicitly stays collision-free too.
     */
    private static int $urutan = 0;

    public function definition(): array
    {
        return [
            'tahun_akademik_id' => TahunAkademik::factory(),
            'mata_kuliah_id' => MataKuliah::factory(),
            'prodi_id' => Prodi::factory(),
            'kode' => 'F'.++self::$urutan,
            'kuota' => fake()->numberBetween(25, 45),
            'terisi' => 0,
            'sks' => fake()->numberBetween(2, 4),
            'mode' => 'tatap_muka',
            'is_case_method' => false,
            'is_team_based_project' => false,
            'status_nilai' => 'belum',
        ];
    }

    /** Flags the class as IKU 7 evidence. */
    public function kolaboratif(): static
    {
        return $this->state([
            'is_case_method' => true,
            'is_team_based_project' => fake()->boolean(),
        ]);
    }
}
