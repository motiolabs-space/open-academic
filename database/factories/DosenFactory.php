<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Models\Akademik\Prodi;
use App\Models\Sdm\Dosen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Dosen> */
class DosenFactory extends Factory
{
    protected $model = Dosen::class;

    public function definition(): array
    {
        return [
            'nidn' => (string) fake()->unique()->numerify('0##########'),
            'nama' => fake()->name(),
            'gelar_depan' => fake()->randomElement([null, 'Dr.', 'Prof. Dr.']),
            'gelar_belakang' => fake()->randomElement(['S.Kom., M.Kom.', 'S.T., M.T.', 'S.Si., M.Sc.']),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'nik' => (string) fake()->unique()->numerify('################'),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-55 years', '-30 years'),
            'jenis_kelamin' => fake()->randomElement(Gender::cases()),
            'telepon' => fake()->phoneNumber(),
            'prodi_id' => Prodi::factory(),
            'jabatan_fungsional' => fake()->randomElement(['Asisten Ahli', 'Lektor', 'Lektor Kepala']),
            'status_kepegawaian' => 'tetap',
            'pendidikan_tertinggi' => EducationLevel::S2,
            'is_praktisi' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A practitioner from industry: the IKU 4 population. They usually hold no
     * NIDN, which is exactly the case the Feeder validator has to catch.
     */
    public function praktisi(): static
    {
        return $this->state([
            'nidn' => null,
            'gelar_depan' => null,
            'status_kepegawaian' => 'luar_biasa',
            'is_praktisi' => true,
            'praktisi_instansi' => fake()->company(),
        ]);
    }
}
