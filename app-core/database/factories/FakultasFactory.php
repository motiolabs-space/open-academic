<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Akademik\Fakultas;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Fakultas> */
class FakultasFactory extends Factory
{
    protected $model = Fakultas::class;

    public function definition(): array
    {
        $nama = fake()->unique()->randomElement([
            'Teknologi Informasi', 'Ekonomi dan Bisnis', 'Hukum', 'Ilmu Sosial dan Politik',
            'Keguruan dan Ilmu Pendidikan', 'Teknik', 'Kesehatan',
        ]);

        return [
            'kode' => 'F'.fake()->unique()->numberBetween(10, 99),
            'nama' => 'Fakultas '.$nama,
            'singkatan' => collect(explode(' ', $nama))
                ->map(fn (string $word): string => mb_substr($word, 0, 1))
                ->implode(''),
        ];
    }
}
