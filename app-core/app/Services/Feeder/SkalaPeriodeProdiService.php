<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\ApplicantStatus;
use App\Models\Akademik\PeriodePerkuliahan;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\SkalaNilaiProdi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Pmb\PmbPendaftar;
use Illuminate\Support\Carbon;

/**
 * Derives the per-programme rows PDDIKTI wants from the campus-wide facts
 * this application keeps.
 *
 * Both derivations are idempotent and additive: a programme that already has
 * rows is left exactly as it is. That is the whole design — the config and
 * the calendar are the default, the table is the record, and an edit to a
 * programme's rows is never overwritten by the next run. See ADR-016.
 */
class SkalaPeriodeProdiService
{
    /**
     * Gives every programme without a grading scale the campus one.
     *
     * Score bands come from config/academic.php, highest letter first: a
     * letter's upper bound is the lower bound of the letter above it, and the
     * top letter reaches 100. The scale takes effect from the campus's first
     * term, or today on an installation with no calendar yet.
     *
     * @return int rows created
     */
    public function turunkanSkala(): int
    {
        $skala = config('academic.grading.scale', []);

        if ($skala === []) {
            return 0;
        }

        $mulai = TahunAkademik::query()->min('tanggal_mulai');
        $berlakuMulai = $mulai !== null ? Carbon::parse($mulai)->toDateString() : Carbon::today()->toDateString();

        $dibuat = 0;

        Prodi::query()
            ->whereDoesntHave('skalaNilai')
            ->each(function (Prodi $prodi) use ($skala, $berlakuMulai, &$dibuat): void {
                $batasAtas = 100.0;

                foreach ($skala as $baris) {
                    SkalaNilaiProdi::create([
                        'prodi_id' => $prodi->id,
                        'nilai_huruf' => $baris['letter'],
                        'nilai_indeks' => (float) $baris['weight'],
                        'bobot_minimum' => (float) $baris['min_score'],
                        'bobot_maksimum' => $batasAtas,
                        'berlaku_mulai' => $berlakuMulai,
                    ]);

                    $batasAtas = (float) $baris['min_score'];
                    $dibuat++;
                }
            });

        return $dibuat;
    }

    /**
     * Gives every programme without a teaching period for the term one.
     *
     * Dates and the number of teaching weeks come from the term and the
     * attendance config; the intake figures from the admission records of
     * that term, or null where the programme ran no admission wave — "not
     * counted" rather than "nobody applied".
     *
     * @return int rows created
     */
    public function turunkanPeriode(TahunAkademik $term): int
    {
        $dibuat = 0;

        Prodi::query()
            ->whereDoesntHave('periodePerkuliahan', fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->each(function (Prodi $prodi) use ($term, &$dibuat): void {
                PeriodePerkuliahan::create([
                    'prodi_id' => $prodi->id,
                    'tahun_akademik_id' => $term->id,
                    'tanggal_awal' => $term->tanggal_mulai,
                    'tanggal_akhir' => $term->tanggal_selesai,
                    'jumlah_minggu_pertemuan' => (int) config('academic.attendance.meetings_per_term', 16),
                    'metode_tatap_muka' => true,
                ] + $this->angkaPmb($prodi, $term));

                $dibuat++;
            });

        return $dibuat;
    }

    /**
     * Intake figures for one programme in one term, from the admission module.
     *
     * @return array<string, int|null>
     */
    private function angkaPmb(Prodi $prodi, TahunAkademik $term): array
    {
        $pendaftar = PmbPendaftar::query()
            ->whereHas('gelombang', fn ($q) => $q->where('tahun_akademik_id', $term->id));

        if (!(clone $pendaftar)->exists()) {
            return [
                'pendaftar_ikut_seleksi' => null,
                'pendaftar_lulus_seleksi' => null,
                'daftar_ulang' => null,
                'mengundurkan_diri' => null,
            ];
        }

        $diterima = fn () => (clone $pendaftar)->where('prodi_diterima_id', $prodi->id);

        return [
            'pendaftar_ikut_seleksi' => (clone $pendaftar)
                ->where('prodi_pilihan_1_id', $prodi->id)
                ->whereNotIn('status', [ApplicantStatus::Draf->value, ApplicantStatus::Mendaftar->value, ApplicantStatus::Verifikasi->value, ApplicantStatus::Batal->value])
                ->count(),
            'pendaftar_lulus_seleksi' => $diterima()
                ->whereIn('status', [ApplicantStatus::Lulus->value, ApplicantStatus::DaftarUlang->value, ApplicantStatus::Mahasiswa->value])
                ->count(),
            'daftar_ulang' => $diterima()
                ->whereIn('status', [ApplicantStatus::DaftarUlang->value, ApplicantStatus::Mahasiswa->value])
                ->count(),
            'mengundurkan_diri' => $diterima()
                ->where('status', ApplicantStatus::Batal->value)
                ->count(),
        ];
    }
}
