<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Sdm\AngkaKreditManualData;
use App\Enums\SumberAngkaKredit;
use App\Enums\UnsurAngkaKredit;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\AngkaKreditService;
use Illuminate\Database\Seeder;

/**
 * A credit ledger with something in it.
 *
 * Every demo lecturer gets a NUPTK — the penyetaraan sheet matches by it, and
 * a demo whose sheet is empty because nobody has one shows nothing. The first
 * lecturer, whose personnel file RiwayatKepegawaianSeeder filled, also gets
 * five converted semesters from before the application existed (SKS × 4),
 * with enough community service that the 10 % cap visibly bites.
 *
 * Derived lines — endorsed BKD, master's and doctoral degrees — are then
 * assembled for everybody, the same way the button and the command do it.
 *
 * After KepegawaianSeeder (ranks, degrees, the endorsed report) and
 * RiwayatKepegawaianSeeder.
 */
class AngkaKreditSeeder extends Seeder
{
    public function run(): void
    {
        $dosen = Dosen::whereNotNull('nidn')->orderBy('id')->get();
        $staf = Staff::where('email', 'admin@demo.test')->first() ?? Staff::orderBy('id')->first();

        if ($dosen->isEmpty() || $staf === null) {
            return;
        }

        foreach ($dosen as $i => $satu) {
            $satu->update(['nuptk' => sprintf('%016d', 7_100_000_000_000_000 + $satu->id)]);
        }

        $ak = app(AngkaKreditService::class);
        $utama = $dosen->first();

        // Semesters after the rank's TMT (three years back), from before 2026.
        $mulai = now()->subYears(3)->year;

        $konversi = [
            [$mulai, '1', UnsurAngkaKredit::Pengajaran, 1200],
            [$mulai, '1', UnsurAngkaKredit::Penelitian, 400],
            [$mulai, '1', UnsurAngkaKredit::Pengabdian, 600],
            [$mulai + 1, '2', UnsurAngkaKredit::Pengabdian, 1200],
            [$mulai, '2', UnsurAngkaKredit::Pengajaran, 1100],
            [$mulai, '2', UnsurAngkaKredit::Penelitian, 500],
            [$mulai + 1, '1', UnsurAngkaKredit::Pengajaran, 1200],
            [$mulai + 1, '1', UnsurAngkaKredit::Penunjang, 300],
            [$mulai + 1, '2', UnsurAngkaKredit::Penelitian, 600],
        ];

        foreach ($konversi as [$tahun, $semester, $unsur, $sks]) {
            $kode = $tahun.$semester;

            // Only what the regulation converts: performance begun before 2026.
            if ((int) $kode >= 20252) {
                continue;
            }

            $ak->catatManual($utama, $staf, new AngkaKreditManualData(
                sumber: SumberAngkaKredit::KonversiManual,
                unsur: $unsur,
                semesterKode: $kode,
                sksRatus: $sks,
                keterangan: 'Konversi BKD lama (arsip LKD)',
            ));
        }

        $ak->catatManual($utama, $staf, new AngkaKreditManualData(
            sumber: SumberAngkaKredit::Penyesuaian,
            unsur: UnsurAngkaKredit::Penunjang,
            nilaiRatus: -200,
            tanggal: now()->subYears(2)->toDateString(),
            keterangan: 'Kepanitiaan dies natalis tercatat dua kali pada arsip BKD.',
        ));

        $ak->susunSemua();
    }
}
