<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KrsStatus;
use App\Models\Akademik\Krs;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Krs> */
class KrsFactory extends Factory
{
    protected $model = Krs::class;

    public function definition(): array
    {
        return [
            'mahasiswa_id' => Mahasiswa::factory(),
            'tahun_akademik_id' => TahunAkademik::factory(),
            'semester_ke' => fake()->numberBetween(1, 8),
            'status' => KrsStatus::Draft,
            'total_sks' => 0,
            'batas_sks' => 24,
            'ips_acuan' => fake()->randomFloat(2, 2.5, 4.0),
        ];
    }

    public function diajukan(): static
    {
        return $this->state([
            'status' => KrsStatus::Diajukan,
            'diajukan_at' => now(),
        ]);
    }

    public function disetujui(): static
    {
        return $this->state([
            'status' => KrsStatus::Disetujui,
            'diajukan_at' => now()->subDay(),
            'disetujui_at' => now(),
        ]);
    }
}
