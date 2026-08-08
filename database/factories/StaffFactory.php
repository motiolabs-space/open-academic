<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sdm\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Staff> */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'nip' => (string) fake()->unique()->numerify('##################'),
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'telepon' => fake()->phoneNumber(),
            'jabatan' => fake()->randomElement(['Staf Administrasi', 'Kepala Bagian', 'Operator']),
            'unit' => fake()->randomElement(['BAAK', 'Keuangan', 'Rektorat', 'TIK']),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function nonAktif(): static
    {
        return $this->state(['is_active' => false]);
    }
}
