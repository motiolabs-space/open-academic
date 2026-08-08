<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<TahunAkademik> */
class TahunAkademikFactory extends Factory
{
    protected $model = TahunAkademik::class;

    public function definition(): array
    {
        return $this->attributes((int) date('Y'), SemesterType::Ganjil);
    }

    /** Build a term from the PDDIKTI encoding, with a sane calendar. */
    public function term(int $tahunMulai, SemesterType $semester): static
    {
        return $this->state(fn (): array => $this->attributes($tahunMulai, $semester));
    }

    /** @return array<string, mixed> */
    private function attributes(int $tahunMulai, SemesterType $semester): array
    {
        // Odd terms open in September, even terms the following February.
        $mulai = $semester === SemesterType::Ganjil
            ? Carbon::create($tahunMulai, 9, 1)
            : Carbon::create($tahunMulai + 1, 2, 1);

        $selesai = (clone $mulai)->addMonths(5)->endOfMonth();

        return [
            'kode' => $semester->termCode($tahunMulai),
            'tahun_mulai' => $tahunMulai,
            'semester' => $semester,
            'nama' => $semester->academicYearLabel($tahunMulai),
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'krs_mulai' => (clone $mulai)->subWeeks(3),
            'krs_selesai' => (clone $mulai)->subWeek(),
            'krs_perubahan_selesai' => (clone $mulai)->addWeeks(2),
            'nilai_mulai' => (clone $selesai)->subWeeks(3),
            'nilai_selesai' => (clone $selesai)->addWeeks(2),
            'is_active' => false,
            'is_locked' => false,
        ];
    }

    public function aktif(): static
    {
        return $this->state(['is_active' => true]);
    }

    /**
     * Anchors the calendar so the term is already a third of the way through,
     * whatever today's date happens to be.
     *
     * A demo campus whose active term has not started yet shows empty
     * attendance grids and an unopened KRS window — technically correct, and
     * useless for demonstrating anything.
     */
    public function berjalan(int $pekanBerjalan = 8): static
    {
        return $this->state(function () use ($pekanBerjalan): array {
            $mulai = Carbon::today()->subWeeks($pekanBerjalan)->startOfWeek();
            $selesai = (clone $mulai)->addWeeks(20);

            return [
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
                'krs_mulai' => (clone $mulai)->subWeeks(3),
                'krs_selesai' => (clone $mulai)->subWeek(),

                // Leave the revision window open so the KRS screen is usable.
                'krs_perubahan_selesai' => Carbon::today()->addWeeks(2),

                'nilai_mulai' => (clone $selesai)->subWeeks(3),
                'nilai_selesai' => (clone $selesai)->addWeeks(2),
            ];
        });
    }

    public function terkunci(): static
    {
        return $this->state(['is_locked' => true]);
    }
}
