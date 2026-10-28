<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\UjiKompetensiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Closing a semester: freezing the per-term academic record.
 *
 * IPS and IPK are already recomputed every time a lecturer finalises a class
 * (PenilaianService::finalisasi). What was missing is the freeze — nothing ever
 * set `status_mahasiswa.is_final`, and that flag is not decoration:
 * `BatasSksCalculator::semesterAcuan()` will only read a *frozen* record when
 * deciding next semester's credit ceiling.
 *
 * The consequence of leaving it unset is silent and total. Every student falls
 * back to `default_credits` forever, so the IPS-based credit ladder — one of
 * the system's headline academic rules — never engages at all. Nothing errors.
 * The demo campus hid it because its seeder writes those rows already frozen.
 *
 * Freezing is deliberately a separate administrative act rather than something
 * that happens when the last lecturer clicks finalise: a grade correction in
 * the week after finalisation is ordinary, and it must still be possible
 * without an audited unfreeze.
 */
class PenutupanSemesterService
{
    public function __construct(
        private readonly IndeksPrestasiCalculator $indeks,
        private readonly UjiKompetensiService $ukom,
    ) {}

    /**
     * What closing this term would do, without doing it.
     *
     * @return array{siap: Collection<int, StatusMahasiswa>, terhalang: Collection<int, array{status: StatusMahasiswa, alasan: string}>, sudah_final: int, kelas_belum_final: Collection<int, KelasKuliah>}
     */
    public function pratinjau(TahunAkademik $term): array
    {
        $kelasBelumFinal = KelasKuliah::query()
            ->with('mataKuliah')
            ->where('tahun_akademik_id', $term->id)
            ->where('status_nilai', '!=', 'final')
            ->whereHas('krsDetail')
            ->get();

        $belumFinalIds = $kelasBelumFinal->pluck('id')->all();

        $status = StatusMahasiswa::query()
            ->with('mahasiswa')
            ->where('tahun_akademik_id', $term->id)
            ->get();

        $tertundaPerMahasiswa = $this->kelasTertunda($term, $belumFinalIds);

        $siap = collect();
        $terhalang = collect();
        $sudahFinal = 0;

        foreach ($status as $baris) {
            if ($baris->is_final) {
                $sudahFinal++;

                continue;
            }

            $tertunda = $tertundaPerMahasiswa->get($baris->mahasiswa_id, collect());

            if ($tertunda->isEmpty()) {
                $siap->push($baris);

                continue;
            }

            $terhalang->push([
                'status' => $baris,
                'alasan' => $tertunda->count().' kelas belum difinalisasi dosennya: '
                    .$tertunda->take(3)->implode(', ')
                    .($tertunda->count() > 3 ? ', dan lainnya' : ''),
            ]);
        }

        return [
            'siap' => $siap,
            'terhalang' => $terhalang,
            'sudah_final' => $sudahFinal,
            'kelas_belum_final' => $kelasBelumFinal,
        ];
    }

    /**
     * Freezes the records that are ready, leaves the rest alone.
     *
     * Partial by design. A campus of five thousand always has one straggling
     * lecturer, and refusing to close anything until every last class is in
     * would mean nobody's credit ceiling updates — which is the exact failure
     * this method exists to fix.
     *
     * Idempotent: a record already frozen is skipped, not recomputed. It is a
     * record; silently rewriting an issued KHS is not something a batch job
     * should be able to do.
     *
     * Students waiting on the competency examination take no courses, so no
     * KRS ever creates their record for the term. It is written here, at the
     * moment every other record for the term is frozen, so the term's activity
     * report to PDDIKTI is complete when it is sent.
     *
     * @return array{dibekukan: int, terhalang: int, dilewati: int, menunggu_ukom: int}
     */
    public function tutup(TahunAkademik $term, Staff $staff): array
    {
        $menungguUkom = $this->ukom->catatSemesterMenunggu($term);

        $pratinjau = $this->pratinjau($term);

        foreach ($pratinjau['siap'] as $status) {
            DB::transaction(function () use ($status, $term, $staff): void {
                // Recomputed immediately before freezing rather than trusted:
                // the last recalculation happened when some other lecturer
                // finalised, and a correction may have landed since.
                $this->indeks->hitungUlang($status->mahasiswa, $term);

                $status->refresh()->update([
                    'is_final' => true,
                    'finalized_at' => now(),
                ]);

                $status->recordActivity('term_closed', sprintf(
                    'Catatan semester %s dibekukan oleh %s — IPS %s, IPK %s, %d SKS kumulatif.',
                    $term->nama,
                    $staff->nama,
                    number_format((float) $status->ips, 2, ',', '.'),
                    number_format((float) $status->ipk, 2, ',', '.'),
                    $status->sks_kumulatif,
                ));
            });
        }

        return [
            'dibekukan' => $pratinjau['siap']->count(),
            'terhalang' => $pratinjau['terhalang']->count(),
            'dilewati' => $pratinjau['sudah_final'],
            'menunggu_ukom' => $menungguUkom,
        ];
    }

    /**
     * Un-freezes one student's term record.
     *
     * Exceptional and audited. Unfreezing changes a KHS that has already been
     * issued and an IPK the student may have quoted on a scholarship
     * application, so the reason is mandatory and stored.
     */
    public function bukaKembali(StatusMahasiswa $status, Staff $staff, string $alasan): StatusMahasiswa
    {
        if (blank($alasan)) {
            throw new AturanAkademikException('Membuka kembali catatan semester wajib disertai alasan.');
        }

        if (!$status->is_final) {
            throw new AturanAkademikException('Catatan semester ini belum dibekukan.');
        }

        $status->update(['is_final' => false, 'finalized_at' => null]);

        $status->recordActivity('term_reopened', sprintf(
            'Catatan semester dibuka kembali oleh %s. Alasan: %s',
            $staff->nama,
            $alasan,
        ));

        return $status->refresh();
    }

    /**
     * Course codes in this term whose grades the lecturer has not finalised,
     * keyed by student.
     *
     * One query for the whole term. This used to run once per student record,
     * which on a campus of five thousand is five thousand queries for a single
     * preview screen.
     *
     * @param array<int, int> $belumFinalIds
     * @return Collection<int, Collection<int, string>>
     */
    private function kelasTertunda(TahunAkademik $term, array $belumFinalIds): Collection
    {
        if ($belumFinalIds === []) {
            return collect();
        }

        return DB::table('krs_detail')
            ->join('krs', 'krs.id', '=', 'krs_detail.krs_id')
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'krs_detail.kelas_kuliah_id')
            ->join('mata_kuliah', 'mata_kuliah.id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->where('krs.tahun_akademik_id', $term->id)
            ->whereIn('krs_detail.kelas_kuliah_id', $belumFinalIds)
            ->whereNull('krs_detail.deleted_at')
            ->orderBy('krs_detail.id')
            ->get(['krs.mahasiswa_id', 'mata_kuliah.kode'])
            ->groupBy('mahasiswa_id')
            ->map(fn (Collection $baris) => $baris->pluck('kode'));
    }
}
