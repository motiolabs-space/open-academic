<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\Enums\JabatanFungsional;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\JabatanFungsionalDosen;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Writes to a lecturer's own record.
 *
 * Thin by design — most of it is ordinary create-and-list, and pretending
 * otherwise would bury the one rule that is not: exactly one appointment may be
 * the current one, and the flat `jabatan_fungsional` column on `dosen` has to
 * follow it or the two will disagree within a term.
 */
class PortofolioService
{
    /**
     * Records an appointment and, when it is the current one, makes it so.
     *
     * The two writes are one transaction. A rank marked current while the
     * lecturer's own row still names the previous one is the exact discrepancy
     * that turns up on a signature block months later, printed on a document
     * somebody already sent.
     */
    public function catatJabatan(
        Dosen $dosen,
        JabatanFungsional $jabatan,
        string $tmt,
        float $angkaKredit = 0.0,
        ?string $nomorSk = null,
        ?string $tanggalSk = null,
        ?string $dokumenPath = null,
        bool $jadikanBerlaku = true,
    ): JabatanFungsionalDosen {
        return DB::transaction(function () use (
            $dosen, $jabatan, $tmt, $angkaKredit, $nomorSk, $tanggalSk, $dokumenPath, $jadikanBerlaku
        ): JabatanFungsionalDosen {
            if ($jadikanBerlaku) {
                // Released before the new one claims it. The unique index would
                // refuse the insert otherwise, and the refusal would be correct
                // but unhelpful.
                JabatanFungsionalDosen::query()
                    ->where('dosen_id', $dosen->id)
                    ->whereNotNull('dosen_aktif_id')
                    ->update(['dosen_aktif_id' => null]);
            }

            try {
                $baris = JabatanFungsionalDosen::create([
                    'dosen_id' => $dosen->id,
                    'jabatan' => $jabatan,
                    'angka_kredit_ratus' => (int) round($angkaKredit * 100),
                    'nomor_sk' => $nomorSk,
                    'tanggal_sk' => $tanggalSk,
                    'tmt' => $tmt,
                    'dokumen_path' => $dokumenPath,
                    'dosen_aktif_id' => $jadikanBerlaku ? $dosen->id : null,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Two requests raced for the current slot. Whichever lost says so
                // in a sentence rather than a stack trace.
                throw new AturanAkademikException(
                    'Jabatan berlaku untuk dosen ini baru saja diubah. Muat ulang halaman lalu ulangi.',
                );
            }

            if ($jadikanBerlaku) {
                // The flat column exists so a class list and a signature block do
                // not need to walk the ladder. It is a cache of this row, and
                // this is the only place it is written.
                $dosen->update(['jabatan_fungsional' => $jabatan->label()]);
            }

            return $baris;
        });
    }

    /**
     * Makes an existing appointment the current one.
     *
     * Separate from recording, because correcting which rung is current is a
     * different act from being promoted, and conflating them would leave a
     * campus entering history with no way to fix an ordering mistake.
     */
    public function jadikanBerlaku(JabatanFungsionalDosen $jabatan): void
    {
        DB::transaction(function () use ($jabatan): void {
            JabatanFungsionalDosen::query()
                ->where('dosen_id', $jabatan->dosen_id)
                ->whereNotNull('dosen_aktif_id')
                ->update(['dosen_aktif_id' => null]);

            $jabatan->update(['dosen_aktif_id' => $jabatan->dosen_id]);
            $jabatan->dosen->update(['jabatan_fungsional' => $jabatan->jabatan->label()]);
        });
    }
}
