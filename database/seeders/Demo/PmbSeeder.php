<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use Illuminate\Database\Seeder;

/**
 * Two admission waves with applicants spread across the funnel, so the PMB
 * screen opens with a funnel that actually narrows.
 */
class PmbSeeder extends Seeder
{
    public function run(): void
    {
        $term = TahunAkademik::aktif();
        $prodi = Prodi::all();

        $gelombang1 = PmbGelombang::create([
            'tahun_akademik_id' => $term->id,
            'kode' => 'PMB-'.$term->kode.'-G1',
            'nama' => 'Gelombang I — Jalur Prestasi',
            'jalur' => 'prestasi',
            'tanggal_mulai' => $term->tanggal_mulai->copy()->subMonths(6),
            'tanggal_selesai' => $term->tanggal_mulai->copy()->subMonths(4),
            'biaya_pendaftaran' => 250_000,
            'kuota' => 120,
            'is_active' => false,
        ]);

        $gelombang2 = PmbGelombang::create([
            'tahun_akademik_id' => $term->id,
            'kode' => 'PMB-'.$term->kode.'-G2',
            'nama' => 'Gelombang II — Jalur Reguler',
            'jalur' => 'reguler',
            'tanggal_mulai' => $term->tanggal_mulai->copy()->subMonths(3),
            'tanggal_selesai' => $term->tanggal_mulai->copy()->subMonth(),
            'biaya_pendaftaran' => 300_000,
            'kuota' => 200,
            'is_active' => true,
        ]);

        // A funnel that narrows the way a real one does.
        $sebaran = [
            [ApplicantStatus::Mendaftar, 34],
            [ApplicantStatus::Verifikasi, 22],
            [ApplicantStatus::Seleksi, 18],
            [ApplicantStatus::Lulus, 15],
            [ApplicantStatus::TidakLulus, 9],
            [ApplicantStatus::DaftarUlang, 11],
            [ApplicantStatus::Batal, 4],
        ];

        $nomor = 1;

        foreach ([$gelombang1, $gelombang2] as $gelombang) {
            foreach ($sebaran as [$status, $jumlah]) {
                for ($i = 0; $i < intdiv($jumlah, 2); $i++) {
                    $pilihan1 = $prodi->random();

                    PmbPendaftar::create([
                        'pmb_gelombang_id' => $gelombang->id,
                        'nomor_pendaftaran' => sprintf('%s-%04d', $gelombang->kode, $nomor++),
                        'nama' => fake()->name(),
                        'email' => fake()->unique()->safeEmail(),
                        'telepon' => fake()->phoneNumber(),

                        // Applicants still early in the funnel have not yet
                        // submitted a NIK — precisely the gap the Feeder
                        // readiness check surfaces before enrolment.
                        'nik' => $status->funnelStep() >= 3
                            ? (string) fake()->unique()->numerify('################')
                            : null,

                        'nisn' => (string) fake()->unique()->numerify('##########'),
                        'tempat_lahir' => fake()->city(),
                        'tanggal_lahir' => fake()->dateTimeBetween('-20 years', '-17 years'),
                        'jenis_kelamin' => fake()->randomElement(Gender::cases()),
                        'alamat' => fake()->streetAddress(),
                        'asal_sekolah' => 'SMA Negeri '.fake()->numberBetween(1, 20).' '.fake()->city(),
                        'tahun_lulus_sekolah' => (int) date('Y'),
                        'prodi_pilihan_1_id' => $pilihan1->id,
                        'prodi_pilihan_2_id' => $prodi->where('id', '!=', $pilihan1->id)->first()?->id,
                        'prodi_diterima_id' => $status->funnelStep() >= 3 ? $pilihan1->id : null,
                        'status' => $status,
                        'nilai_seleksi' => $status->funnelStep() >= 2 ? fake()->randomFloat(2, 55, 95) : null,
                    ]);
                }
            }
        }
    }
}
