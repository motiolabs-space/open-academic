<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MataKuliah> */
class MataKuliahFactory extends Factory
{
    protected $model = MataKuliah::class;

    public function definition(): array
    {
        $sksTeori = fake()->numberBetween(2, 3);
        $sksPraktik = fake()->randomElement([0, 0, 1]);

        return [
            'prodi_id' => Prodi::factory(),
            'kode' => 'MK'.fake()->unique()->numberBetween(1000, 9999),
            'nama' => fake()->unique()->words(3, true),
            'sks_teori' => $sksTeori,
            'sks_praktik' => $sksPraktik,
            'sks_praktik_lapangan' => 0,
            'sks' => $sksTeori + $sksPraktik,
            'is_active' => true,
        ];
    }
}
