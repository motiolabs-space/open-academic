<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Prodi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prodi> */
class ProdiFactory extends Factory
{
    protected $model = Prodi::class;

    public function definition(): array
    {
        return [
            'fakultas_id' => Fakultas::factory(),
            'kode' => (string) fake()->unique()->numberBetween(55201, 55299),

            // Feeder keys on id_sms; the demo data uses a plausible UUID so the
            // sync screens have something realistic to display.
            'kode_pddikti' => fake()->uuid(),

            'nama' => fake()->unique()->randomElement([
                'Informatika', 'Sistem Informasi', 'Manajemen', 'Akuntansi',
                'Ilmu Hukum', 'Teknik Industri', 'Kesehatan Masyarakat',
            ]),
            'jenjang' => EducationLevel::S1,
            'gelar_pendek' => 'S.Kom.',
            'gelar_panjang' => 'Sarjana Komputer',
            'akreditasi' => fake()->randomElement(['Unggul', 'Baik Sekali', 'Baik', 'B']),
            'sks_lulus' => 144,
            'is_active' => true,
        ];
    }
}
