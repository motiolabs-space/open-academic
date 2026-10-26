<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Akademik\PenyusunanUjianData;
use App\Enums\JenisUjianKelas;
use App\Models\Akademik\TahunAkademik;
use App\Services\Akademik\JadwalUjianService;
use Illuminate\Database\Seeder;

/**
 * The active term's mid-term timetable, published.
 *
 * Produced the way a registrar would: the bulk scheduler lays out every class
 * over two weeks from week eight, and the result is published through the same
 * clash gate as the screen. If the demo data ever made two exams collide for a
 * student, this seeder fails rather than shipping a campus that breaks its own
 * rule — the same lesson the class timetable learnt (see JadwalService).
 *
 * The finals are left unscheduled, so the screen still has something to
 * arrange in a demo.
 */
class JadwalUjianSeeder extends Seeder
{
    public function run(JadwalUjianService $ujian): void
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            return;
        }

        $mulai = $term->tanggal_mulai->copy()->addWeeks(7)->startOfWeek();

        $ujian->simpanRencana(new PenyusunanUjianData(
            tahunAkademikId: $term->id,
            jenis: JenisUjianKelas::Uts,
            prodiId: null,
            tanggalMulai: $mulai->toDateString(),
            tanggalSelesai: $mulai->copy()->addDays(11)->toDateString(),
        ));

        $ujian->terbitkan($term, JenisUjianKelas::Uts);
    }
}
