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

    public function definition(): array
    {
        return [
            'tahun_akademik_id' => TahunAkademik::factory(),
            'mata_kuliah_id' => MataKuliah::factory(),
            'prodi_id' => Prodi::factory(),
            'kode' => fake()->randomElement(['A', 'B', 'C']),
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
