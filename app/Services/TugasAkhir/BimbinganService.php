<?php

declare(strict_types=1);

namespace App\Services\TugasAkhir;

use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\Bimbingan;
use App\Models\TugasAkhir\TugasAkhir;

/**
 * The consultation log.
 *
 * On paper this is a card the student carries and the supervisor signs. That
 * signature is the whole mechanism: it is the difference between a project that
 * was supervised and one that was merely assigned, and it is the evidence a
 * defence gets scheduled against.
 *
 * So the two halves stay separate here. The student writes what was discussed;
 * only the named supervisor can mark it as having happened. Collapse those and
 * the minimum-consultation rule before a defence is certified by the person it
 * constrains, which makes it decoration.
 */
class BimbinganService
{
    /** Records a consultation the student is reporting. */
    public function catat(
        TugasAkhir $ta,
        Dosen $dosen,
        string $tanggal,
        string $topik,
        ?string $uraian = null,
    ): Bimbingan {
        if ($ta->status !== TugasAkhirStatus::Dibimbing) {
            throw new AturanAkademikException(
                'Log bimbingan hanya dapat diisi setelah pembimbing ditetapkan. Status saat ini: '
                    .$ta->status->label().'.',
            );
        }

        $this->pastikanPembimbing($ta, $dosen);

        if ($tanggal > now()->toDateString()) {
            throw new AturanAkademikException('Tanggal bimbingan tidak boleh di masa depan.');
        }

        return Bimbingan::create([
            'tugas_akhir_id' => $ta->id,
            'dosen_id' => $dosen->id,
            'tanggal' => $tanggal,
            'topik' => $topik,
            'uraian' => $uraian,
            'disetujui' => false,
        ]);
    }

    /**
     * The supervisor's sign-off.
     *
     * Only the lecturer named on the row may give it. Letting any supervisor
     * approve any entry would mean a second supervisor could unknowingly
     * certify meetings they never attended.
     */
    public function setujui(Bimbingan $bimbingan, Dosen $dosen, ?string $catatan = null): Bimbingan
    {
        if ($bimbingan->dosen_id !== $dosen->id) {
            throw new AturanAkademikException(
                'Hanya dosen yang tercatat pada log bimbingan ini yang dapat menyetujuinya.',
            );
        }

        if ($bimbingan->disetujui) {
            throw new AturanAkademikException('Log bimbingan ini sudah disetujui.');
        }

        $bimbingan->update([
            'disetujui' => true,
            'disetujui_at' => now(),
            'catatan_dosen' => $catatan,
        ]);

        return $bimbingan->refresh();
    }

    /**
     * Withdraws a sign-off.
     *
     * Kept available because approving the wrong row is an ordinary mistake,
     * and the alternative — deleting and re-entering — loses what the student
     * wrote.
     */
    public function batalkanPersetujuan(Bimbingan $bimbingan, Dosen $dosen): Bimbingan
    {
        if ($bimbingan->dosen_id !== $dosen->id) {
            throw new AturanAkademikException(
                'Hanya dosen yang tercatat pada log bimbingan ini yang dapat mencabut persetujuannya.',
            );
        }

        $bimbingan->update(['disetujui' => false, 'disetujui_at' => null]);

        return $bimbingan->refresh();
    }

    /**
     * Deletes an entry the student got wrong.
     *
     * Only while unapproved. Once signed it is part of the record a defence was
     * scheduled against, and removing it retroactively changes whether that
     * defence was allowed to happen.
     */
    public function hapus(Bimbingan $bimbingan): void
    {
        if ($bimbingan->disetujui) {
            throw new AturanAkademikException(
                'Log bimbingan yang sudah disetujui tidak dapat dihapus — mintalah dosen mencabut '
                    .'persetujuannya lebih dulu.',
            );
        }

        $bimbingan->delete();
    }

    private function pastikanPembimbing(TugasAkhir $ta, Dosen $dosen): void
    {
        $terdaftar = $ta->relationLoaded('pembimbing')
            ? $ta->pembimbing->contains('dosen_id', $dosen->id)
            : $ta->pembimbing()->where('dosen_id', $dosen->id)->exists();

        if (!$terdaftar) {
            throw new AturanAkademikException(
                $dosen->namaLengkap().' bukan pembimbing tugas akhir ini.',
            );
        }
    }
}
