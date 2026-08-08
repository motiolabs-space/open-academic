<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\Prodi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Kurikulum> */
class KurikulumFactory extends Factory
{
    protected $model = Kurikulum::class;

    public function definition(): array
    {
        $tahun = (int) date('Y');

        return [
            'prodi_id' => Prodi::factory(),
            'kode' => 'KUR'.$tahun,
            'nama' => 'Kurikulum OBE '.$tahun,
            'tahun_mulai' => $tahun,
            'tahun_selesai' => null,
            'sks_wajib' => 120,
            'sks_pilihan' => 24,
            'sks_lulus' => 144,
            'is_active' => true,
        ];
    }
}
