<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\PerolehanBaris;
use App\Enums\StatusKonversi;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\Nilai;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * What a student has actually earned — the one place that decides.
 *
 * Three parts of this system need the same answer: the transcript prints it, the
 * IPK is computed from it, and the graduation checklist is measured against it.
 * Until this class existed, each of them worked it out for itself, with the same
 * "best attempt per course" logic written out three times in three files.
 *
 * That was already a latent bug — edit one and the other two silently disagree,
 * producing a transcript showing credits the graduation screen does not count.
 * Adding recognised credit would have made it four copies, so the copies were
 * collapsed first and the new feature added once.
 *
 * Two rules live here and nowhere else:
 *
 *  - **Best attempt per course.** A repeated course contributes once, at its
 *    highest grade.
 *  - **Work done here beats recognition.** If a course somehow has both a grade
 *    and an approved conversion, the grade wins. KrsService prevents the pair
 *    from arising going forward; this decides what to do about data that
 *    already has it, rather than leaving it to whichever query ran first.
 */
class PerolehanAkademik
{
    /**
     * @return Collection<int, PerolehanBaris> keyed by mata_kuliah_id
     */
    public function untuk(Mahasiswa $mahasiswa): Collection
    {
        return $this->untukBanyak(collect([$mahasiswa]))[$mahasiswa->id] ?? collect();
    }

    /**
     * The same answer for many students, in a fixed number of queries.
     *
     * The graduation screen runs this across a cohort; per-student would be two
     * round trips each.
     *
     * @param Collection<int, Mahasiswa> $mahasiswa
     * @return Collection<int, Collection<int, PerolehanBaris>> keyed by student id
     */
    public function untukBanyak(Collection $mahasiswa): Collection
    {
        $ids = $mahasiswa->pluck('id')->all();

        if ($ids === []) {
            return collect();
        }

        $nilai = Nilai::query()
            ->with(['krsDetail.krs.tahunAkademik', 'kelasKuliah.mataKuliah'])
            ->whereIn('mahasiswa_id', $ids)
            ->final()
            ->whereNotNull('nilai_huruf')
            ->get()
            ->groupBy('mahasiswa_id');

        $konversi = KonversiKredit::query()
            ->with('mataKuliah')
            ->whereIn('mahasiswa_id', $ids)
            ->where('status', StatusKonversi::Disetujui->value)
            ->get()
            ->groupBy('mahasiswa_id');

        return collect($ids)->mapWithKeys(fn (int $id): array => [
            $id => $this->gabungkan(
                $nilai->get($id) ?? collect(),
                $konversi->get($id) ?? collect(),
            ),
        ]);
    }

    /**
     * Credits, grade points, and the average, from an already-built list.
     *
     * @param Collection<int, PerolehanBaris> $baris
     * @return array{sks: int, sksLulus: int, ipk: float, sksKonversi: int}
     */
    public function ringkas(Collection $baris): array
    {
        $lulus = $baris->filter(fn (PerolehanBaris $b): bool => $b->lulus);

        /*
         * Credits and the average answer different questions, and are counted
         * differently on purpose.
         *
         * **Credits towards graduation exclude failures.** Nobody graduates on
         * a course they failed.
         *
         * **The average includes them.** A failed course that was never
         * repeated is part of the record, and dropping it would let a student
         * improve their IPK by failing things — the more they failed, the
         * higher the average of what remained.
         *
         * These two used to disagree. IndeksPrestasiCalculator stored an IPK
         * over every finalised course while YudisiumService measured the
         * graduation requirement against an IPK over passes only, so a student
         * could be shown 2,0 on their record and 4,0 on the graduation screen.
         * Both numbers were called "IPK". Now there is one.
         *
         * Conversions are excluded from the average when the campus has not
         * opted in, and then from the denominator too — leaving them in the
         * bottom half while removing them from the top would quietly depress
         * every transferring student's average.
         */
        $untukIpk = $baris->filter(fn (PerolehanBaris $b): bool => $b->masukIpk);
        $sksIpk = (int) $untukIpk->sum(fn (PerolehanBaris $b): int => $b->sks);

        return [
            'sks' => (int) $baris->sum(fn (PerolehanBaris $b): int => $b->sks),
            'sksLulus' => (int) $lulus->sum(fn (PerolehanBaris $b): int => $b->sks),
            'ipk' => $sksIpk > 0
                ? round($untukIpk->sum(fn (PerolehanBaris $b): float => $b->mutu()) / $sksIpk, 2)
                : 0.0,
            'sksKonversi' => (int) $lulus
                ->filter(fn (PerolehanBaris $b): bool => $b->konversi)
                ->sum(fn (PerolehanBaris $b): int => $b->sks),
        ];
    }

    /** Convenience for callers that want both in one step. */
    public function ringkasUntuk(Mahasiswa $mahasiswa): array
    {
        return $this->ringkas($this->untuk($mahasiswa));
    }

    /**
     * @param Collection<int, Nilai> $nilai
     * @param Collection<int, KonversiKredit> $konversi
     * @return Collection<int, PerolehanBaris>
     */
    private function gabungkan(Collection $nilai, Collection $konversi): Collection
    {
        $hasil = collect();

        // Conversions first, so a taught grade overwrites one for the same
        // course rather than the other way round.
        foreach ($konversi as $k) {
            $hasil->put($k->mata_kuliah_id, $this->dariKonversi($k));
        }

        foreach ($this->terbaikPerMataKuliah($nilai) as $n) {
            $hasil->put((int) $n->kelasKuliah->mata_kuliah_id, $this->dariNilai($n));
        }

        return $hasil;
    }

    /**
     * @param Collection<int, Nilai> $nilai
     * @return Collection<int, Nilai>
     */
    private function terbaikPerMataKuliah(Collection $nilai): Collection
    {
        return EloquentCollection::make($nilai->all())
            ->groupBy(fn (Nilai $n): int => (int) $n->kelasKuliah->mata_kuliah_id)
            ->map(fn (Collection $percobaan): Nilai => $percobaan->sortByDesc(
                fn (Nilai $n): float => (float) $n->bobot,
            )->first())
            ->values();
    }

    private function dariNilai(Nilai $n): PerolehanBaris
    {
        return new PerolehanBaris(
            mataKuliahId: (int) $n->kelasKuliah->mata_kuliah_id,
            kode: (string) $n->kelasKuliah->mataKuliah->kode,
            nama: (string) $n->kelasKuliah->mataKuliah->nama,

            // Credits from the study plan row, not from the course master: a
            // course whose credit value was changed later must not retroactively
            // alter a transcript already issued.
            sks: (int) ($n->krsDetail->sks ?? 0),

            huruf: $n->nilai_huruf?->value,
            bobot: (float) $n->bobot,
            lulus: $n->nilai_huruf?->isPassing() ?? false,
            konversi: false,
            tanda: null,
            periode: $n->krsDetail?->krs?->tahunAkademik?->nama,
            masukIpk: true,
        );
    }

    private function dariKonversi(KonversiKredit $k): PerolehanBaris
    {
        return new PerolehanBaris(
            mataKuliahId: (int) $k->mata_kuliah_id,
            kode: (string) $k->mataKuliah->kode,
            nama: (string) $k->mataKuliah->nama,
            sks: (int) $k->sks_diakui,
            huruf: $k->nilai_huruf,
            bobot: (float) $k->bobot,

            /*
             * A granted conversion is a pass by definition.
             *
             * The campus decided the requirement was met; whether a letter grade
             * was attached is a separate question, and RPL frequently carries
             * none. Treating an ungraded recognition as a fail would mean
             * granting credit and then refusing to count it.
             */
            lulus: true,

            konversi: true,
            tanda: $k->jenis->tanda(),
            periode: $k->asal_institusi ?? $k->jenis->label(),
            masukIpk: (bool) config('academic.konversi.hitung_ipk') && $k->bobot !== null,
        );
    }
}
