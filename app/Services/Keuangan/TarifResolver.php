<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tarif;
use Illuminate\Support\Collection;

/**
 * Works out what one student actually owes, from a matrix of overlapping rules.
 *
 * The matrix is deliberately sparse: a campus writes one general UKT row and
 * then overrides it for a particular programme, intake or band. So several rows
 * match any given student, and picking among them is the whole job.
 *
 * **One row per component wins — they are not summed.** That distinction is not
 * academic: a 5,000,000 fallback plus a 7,000,000 programme override billed
 * together is 12,000,000, and the student is simply told to pay it. Summing was
 * exactly the defect this class was written to remove.
 *
 * The winner is the most specific match, which is what `Tarif::spesifisitas()`
 * counts. A tie goes to the newest row, on the reasoning that a fee schedule
 * entered later is the correction.
 */
class TarifResolver
{
    /**
     * The fee lines that apply to this student for this term — one per component.
     *
     * @return Collection<int, Tarif>
     */
    public function untuk(Mahasiswa $mahasiswa, TahunAkademik $term): Collection
    {
        return Tarif::query()
            // The simulator names the programme each row belongs to.
            ->with('prodi')
            ->aktif()

            // A schedule that expired two years ago must not still be billed.
            // The scope existed from the start; the callers simply never used it.
            ->berlakuPada($term->kode)

            // Every dimension a row declares must match; a null is a wildcard.

            ->where(fn ($q) => $q->whereNull('prodi_id')->orWhere('prodi_id', $mahasiswa->prodi_id))
            ->where(fn ($q) => $q->whereNull('angkatan')->orWhere('angkatan', $mahasiswa->angkatan))
            ->where(fn ($q) => $q->whereNull('jalur_masuk')->orWhere('jalur_masuk', $mahasiswa->jalur_masuk))
            ->where(fn ($q) => $q->whereNull('golongan_ukt')->orWhere('golongan_ukt', $mahasiswa->golongan_ukt))
            ->get()
            ->groupBy('komponen')
            ->map(fn (Collection $kandidat): Tarif => $kandidat
                ->sortByDesc(fn (Tarif $t): string => sprintf('%02d-%012d', $t->spesifisitas(), $t->id))
                ->first())
            ->values();
    }

    /** Total payable for the term, in whole rupiah. */
    public function total(Mahasiswa $mahasiswa, TahunAkademik $term): int
    {
        return (int) $this->untuk($mahasiswa, $term)->sum('nominal');
    }

    /**
     * The same answer, with the rows that lost, for a screen that has to explain
     * itself.
     *
     * A finance officer about to bill five thousand people deserves to see why
     * a particular figure came out — "this override beat that fallback" — rather
     * than a number with no provenance.
     *
     * @return Collection<int, array{terpilih: Tarif, dikalahkan: Collection<int, Tarif>}>
     */
    public function rincian(Mahasiswa $mahasiswa, TahunAkademik $term): Collection
    {
        return Tarif::query()
            // The simulator names the programme each row belongs to.
            ->with('prodi')
            ->aktif()
            ->berlakuPada($term->kode)
            ->where(fn ($q) => $q->whereNull('prodi_id')->orWhere('prodi_id', $mahasiswa->prodi_id))
            ->where(fn ($q) => $q->whereNull('angkatan')->orWhere('angkatan', $mahasiswa->angkatan))
            ->where(fn ($q) => $q->whereNull('jalur_masuk')->orWhere('jalur_masuk', $mahasiswa->jalur_masuk))
            ->where(fn ($q) => $q->whereNull('golongan_ukt')->orWhere('golongan_ukt', $mahasiswa->golongan_ukt))
            ->get()
            ->groupBy('komponen')
            ->map(function (Collection $kandidat): array {
                $urut = $kandidat->sortByDesc(
                    fn (Tarif $t): string => sprintf('%02d-%012d', $t->spesifisitas(), $t->id),
                )->values();

                return ['terpilih' => $urut->first(), 'dikalahkan' => $urut->skip(1)->values()];
            })
            ->values();
    }
}
