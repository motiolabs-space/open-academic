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
            // PDDIKTI-shaped (five digits) but drawn from 80000–89999, a range
            // no test pins by hand. Tests that need a real code — '55201',
            // '57401', '61201' — create it themselves; a factory row landing
            // on the same code broke the unique index at random (PR #22, #34).
            'kode' => (string) fake()->unique()->numberBetween(80000, 89999),

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
