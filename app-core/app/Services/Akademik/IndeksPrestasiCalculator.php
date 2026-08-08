<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Models\Akademik\Nilai;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use Illuminate\Support\Collection;

/**
 * Computes IPS and IPK, and writes them onto the per-term enrolment record.
 *
 * The one rule worth stating plainly: a repeated course counts **once** toward
 * the IPK, using the best grade achieved. Every attempt is still stored and
 * still reported to PDDIKTI — the repeat only stops inflating or deflating the
 * cumulative average by being counted twice.
 */
class IndeksPrestasiCalculator
{
    public function __construct(private readonly PerolehanAkademik $perolehan) {}

    /**
     * Recomputes the term's IPS and the student's cumulative IPK, then stores
     * them. Returns the updated enrolment row.
     */
    public function hitungUlang(Mahasiswa $mahasiswa, TahunAkademik $term): ?StatusMahasiswa
    {
        $status = StatusMahasiswa::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $term->id)
            ->first();

        if ($status === null) {
            return null;
        }

        $nilaiTerm = $this->nilaiFinal($mahasiswa)->filter(
            fn (Nilai $n): bool => $n->krsDetail?->krs?->tahun_akademik_id === $term->id,
        );

        /*
         * The term's own figures stay here; the cumulative ones come from
         * PerolehanAkademik.
         *
         * The split follows the data. An IPS is about one semester, and
         * recognised credit has no semester — a transfer student's prior study
         * did not happen in any term of this campus's calendar. The cumulative
         * totals are exactly where that credit belongs, and exactly where the
         * transcript and the graduation checklist read from too.
         */
        $kumulatif = $this->perolehan->ringkasUntuk($mahasiswa);

        $status->update([
            'sks_semester' => $this->totalSks($nilaiTerm),
            'sks_kumulatif' => $kumulatif['sks'],
            'ips' => $this->rerataTerbobot($nilaiTerm),
            'ipk' => $kumulatif['ipk'],
        ]);

        return $status->refresh();
    }

    /** Recomputes every student who has a grade in the term. */
    public function hitungUlangSeluruhTerm(TahunAkademik $term): int
    {
        $mahasiswaIds = Nilai::query()
            ->final()
            ->whereHas('krsDetail.krs', fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->distinct()
            ->pluck('mahasiswa_id');

        $jumlah = 0;

        foreach (Mahasiswa::whereIn('id', $mahasiswaIds)->cursor() as $mahasiswa) {
            if ($this->hitungUlang($mahasiswa, $term) !== null) {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /** IPS a term would have, without writing anything. */
    public function ipsSementara(Mahasiswa $mahasiswa, TahunAkademik $term): float
    {
        return $this->rerataTerbobot(
            $this->nilaiFinal($mahasiswa)->filter(
                fn (Nilai $n): bool => $n->krsDetail?->krs?->tahun_akademik_id === $term->id,
            ),
        );
    }

    /** @return Collection<int, Nilai> */
    private function nilaiFinal(Mahasiswa $mahasiswa): Collection
    {
        return Nilai::query()
            ->with(['krsDetail.krs', 'kelasKuliah:id,mata_kuliah_id'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->final()
            ->whereNotNull('nilai_huruf')
            ->get();
    }

    /*
     * The best-attempt-per-course rule used to live here as well. It now lives
     * once, in PerolehanAkademik, alongside the two other copies it had grown
     * in YudisiumService and TranskripService.
     */

    /** @param Collection<int, Nilai> $nilai */
    private function rerataTerbobot(Collection $nilai): float
    {
        $sks = $this->totalSks($nilai);

        if ($sks === 0) {
            return 0.0;
        }

        $mutu = $nilai->sum(
            fn (Nilai $n): float => (float) $n->bobot * (int) ($n->krsDetail->sks ?? 0),
        );

        return round($mutu / $sks, 2);
    }

    /** @param Collection<int, Nilai> $nilai */
    private function totalSks(Collection $nilai): int
    {
        return (int) $nilai->sum(fn (Nilai $n): int => (int) ($n->krsDetail->sks ?? 0));
    }
}
