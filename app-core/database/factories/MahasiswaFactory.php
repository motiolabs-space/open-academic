<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Mahasiswa> */
class MahasiswaFactory extends Factory
{
    protected $model = Mahasiswa::class;

    public function definition(): array
    {
        $angkatan = fake()->numberBetween((int) date('Y') - 3, (int) date('Y'));

        return [
            'nim' => (string) fake()->unique()->numerify('##########'),
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'nik' => (string) fake()->unique()->numerify('################'),
            'nisn' => (string) fake()->unique()->numerify('##########'),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-25 years', '-18 years'),
            'jenis_kelamin' => fake()->randomElement(Gender::cases()),
            'telepon' => fake()->phoneNumber(),
            'alamat' => fake()->streetAddress(),
            'kabupaten' => fake()->city(),
            'provinsi' => fake()->state(),
            'kode_pos' => fake()->postcode(),
            'prodi_id' => Prodi::factory(),
            'angkatan' => $angkatan,
            'jalur_masuk' => fake()->randomElement(['Reguler', 'Prestasi', 'Undangan']),
            'status' => StudentStatus::Aktif,
            'nama_ayah' => fake()->name('male'),
            'nama_ibu' => fake()->name('female'),
            'asal_sekolah' => 'SMA Negeri '.fake()->numberBetween(1, 20).' '.fake()->city(),
            'tahun_lulus_sekolah' => $angkatan,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function status(StudentStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    /** Missing NIK — the row the Feeder pre-flight validator must reject. */
    public function tanpaNik(): static
    {
        return $this->state(['nik' => null]);
    }
}
