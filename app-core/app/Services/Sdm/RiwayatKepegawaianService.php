<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\MutasiDosen;
use App\Models\Sdm\PangkatDosen;
use App\Models\Sdm\UnitKerja;
use Illuminate\Support\Facades\DB;

/**
 * The two personnel histories that are more than a list.
 *
 * Distinct from KepegawaianService, which creates accounts and disables them.
 * This one only touches the deeper records added alongside the org chart.
 *
 * Family, languages, organisations, awards and sanctions are flat histories:
 * add a row, edit a row. They need no service, and wrapping them in one would
 * be ceremony. Rank and posting are different — each has a *current* value that
 * something else reads, and keeping that pointer honest is the whole job.
 */
class RiwayatKepegawaianService
{
    /**
     * Promotes a lecturer, retiring whichever rank was current.
     *
     * Both writes in one transaction. The nullable-unique column means the
     * database refuses two current ranks outright, so a half-finished promotion
     * does not leave the lecturer with none — it leaves the promotion undone,
     * which is recoverable and visible.
     *
     * @param array<string, mixed> $data
     */
    public function naikPangkat(Dosen $dosen, array $data): PangkatDosen
    {
        return DB::transaction(function () use ($dosen, $data): PangkatDosen {
            PangkatDosen::where('dosen_aktif_id', $dosen->id)
                ->update(['dosen_aktif_id' => null]);

            return PangkatDosen::create([
                ...$data,
                'dosen_id' => $dosen->id,
                'dosen_aktif_id' => $dosen->id,
            ]);
        });
    }

    /** The rank in force, or null for a lecturer who has never been ranked. */
    public function pangkatBerlaku(Dosen $dosen): ?PangkatDosen
    {
        return PangkatDosen::where('dosen_aktif_id', $dosen->id)->first();
    }

    /**
     * Records a posting and moves the lecturer's current unit with it.
     *
     * The pointer and the history are written together, deliberately. Deriving
     * "current unit" from the latest mutation row would be one fewer column and
     * one more way to be wrong: a back-dated correction would silently move
     * somebody who never moved, and every screen would pay a subquery to ask
     * where a person works.
     *
     * @param array<string, mixed> $data
     */
    public function catatMutasi(Dosen $dosen, array $data): MutasiDosen
    {
        $tujuan = $data['unit_tujuan_id'] ?? null;

        if ($tujuan !== null) {
            $unit = UnitKerja::find($tujuan);

            if ($unit === null || !$unit->is_active) {
                throw new AturanAkademikException(
                    'Unit tujuan tidak ditemukan atau sudah tidak aktif.',
                );
            }
        }

        if (($data['jenis'] ?? null) === 'pindah' && $tujuan === null) {
            throw new AturanAkademikException('Mutasi pindah harus menyebutkan unit tujuan.');
        }

        return DB::transaction(function () use ($dosen, $data, $tujuan): MutasiDosen {
            $mutasi = MutasiDosen::create([
                ...$data,

                // Filled from the pointer rather than trusted from the form:
                // the origin of a move is wherever the person actually is, and
                // a form can be submitted after somebody else already moved them.
                'unit_asal_id' => $data['unit_asal_id'] ?? $dosen->unit_kerja_id,

                'dosen_id' => $dosen->id,
            ]);

            /*
             * A departure clears the unit rather than leaving the last one.
             *
             * Somebody who has left is not still filed under their old bureau —
             * that is how a head count keeps counting people who resigned.
             */
            $dosen->update([
                'unit_kerja_id' => $data['jenis'] === 'keluar' ? null : $tujuan,
            ]);

            return $mutasi;
        });
    }

    /**
     * Everything on one lecturer's personnel file, in one place.
     *
     * @return array<string, mixed>
     */
    public function berkas(Dosen $dosen): array
    {
        $dosen->loadMissing([
            'unitKerja',
            'keluarga',
            'pangkat',
            'mutasi.unitAsal',
            'mutasi.unitTujuan',
            'penghargaanSanksi',
            'bahasa',
            'organisasi',
        ]);

        return [
            'unit' => $dosen->unitKerja,
            'pangkatBerlaku' => $dosen->pangkat->firstWhere('dosen_aktif_id', $dosen->id),
            'pangkat' => $dosen->pangkat->sortByDesc('tmt')->values(),
            'keluarga' => $dosen->keluarga,
            'mutasi' => $dosen->mutasi->sortByDesc('tmt')->values(),

            /*
             * Two lists, not one balance.
             *
             * They share a table because they share every column, and nothing
             * anywhere adds them: an award does not offset a reprimand, and a
             * combined figure would be a judgement the campus never made.
             */
            'penghargaan' => $dosen->penghargaanSanksi->where('jenis', 'penghargaan')->values(),
            'sanksi' => $dosen->penghargaanSanksi->where('jenis', 'sanksi')->values(),

            'bahasa' => $dosen->bahasa,
            'organisasi' => $dosen->organisasi->sortByDesc('tahun_mulai')->values(),
        ];
    }
}
