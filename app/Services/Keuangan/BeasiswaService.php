<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\Enums\StatusPenerima;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaPenerima;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Scholarship schemes and the students who hold them.
 *
 * The awkward part is not granting an award — it is that invoices and awards
 * arrive in either order. A scholarship confirmed in September has to reach an
 * invoice issued in August, and an invoice issued in October has to already know
 * about it. Both paths run through PotonganService so the arithmetic exists once.
 */
class BeasiswaService
{
    public function __construct(private readonly PotonganService $potongan) {}

    /**
     * Grants an award, and applies it to invoices already issued for the terms
     * it covers.
     */
    public function tetapkan(
        Beasiswa $beasiswa,
        Mahasiswa $mahasiswa,
        TahunAkademik $mulai,
        ?TahunAkademik $selesai = null,
        ?string $nomorSk = null,
        ?Staff $staff = null,
    ): BeasiswaPenerima {
        if (!$beasiswa->is_active) {
            throw new AturanAkademikException(
                "Skema beasiswa {$beasiswa->nama} sedang nonaktif dan tidak dapat diberikan.",
            );
        }

        if ($selesai !== null && $selesai->kode < $mulai->kode) {
            throw new AturanAkademikException(
                'Semester selesai tidak boleh mendahului semester mulai.',
            );
        }

        /*
         * The quota is the point of a quota.
         *
         * Counted over live awards only — a scheme for twenty students means
         * twenty at a time, not twenty ever. Without it, a scheme funded for a
         * fixed number is over-committed and nobody discovers it until the
         * sponsor's payment falls short of the discounts already given.
         */
        $sisa = $beasiswa->kuotaTersisa();

        if ($sisa !== null && $sisa < 1) {
            throw new AturanAkademikException(sprintf(
                'Kuota beasiswa %s sudah penuh (%d penerima aktif).',
                $beasiswa->nama,
                $beasiswa->jumlahAktif(),
            ));
        }

        try {
            $penerima = DB::transaction(fn (): BeasiswaPenerima => BeasiswaPenerima::create([
                'beasiswa_id' => $beasiswa->id,
                'mahasiswa_id' => $mahasiswa->id,
                'tahun_akademik_mulai_id' => $mulai->id,
                'tahun_akademik_selesai_id' => $selesai?->id,
                'status' => StatusPenerima::Aktif,
                'nomor_sk' => $nomorSk,
                'diputus_by_staff_id' => $staff?->id,
                'diputus_at' => now(),

                // Claims the (scheme, student) pair; the unique index is what
                // actually prevents the coverage being applied twice.
                'kunci_aktif' => BeasiswaPenerima::kunci($beasiswa->id, $mahasiswa->id),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new AturanAkademikException(
                "{$mahasiswa->nama} sudah menerima beasiswa {$beasiswa->nama} yang masih berjalan.",
            );
        }

        $this->terapkanKeTagihan($penerima, $staff);

        return $penerima->refresh();
    }

    /**
     * Ends an award without disturbing what has already been billed.
     *
     * Prospective on purpose. Reversing past terms would re-raise debts on
     * invoices a student has already settled, months after they reasonably
     * considered the matter closed. Where a reduction genuinely should not have
     * been granted, PotonganService::hapus() reverses that one line and records
     * why — a deliberate act on a named invoice, not a side effect of ending a
     * scheme.
     */
    public function cabut(BeasiswaPenerima $penerima, Staff $staff, string $alasan): BeasiswaPenerima
    {
        if ($penerima->status !== StatusPenerima::Aktif) {
            throw new AturanAkademikException('Hanya penerimaan yang masih aktif yang dapat dicabut.');
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Pencabutan beasiswa wajib disertai alasan.');
        }

        DB::transaction(function () use ($penerima, $staff, $alasan): void {
            $penerima->update([
                'status' => StatusPenerima::Dicabut,
                'catatan' => $alasan,
                'kunci_aktif' => null,
                'diputus_by_staff_id' => $staff->id,
                'diputus_at' => now(),
            ]);

            $penerima->recordActivity('scholarship_revoked', sprintf(
                'Beasiswa %s dicabut oleh %s. Alasan: %s',
                $penerima->beasiswa->nama,
                $staff->nama,
                $alasan,
            ));
        });

        return $penerima->refresh();
    }

    /** Closes an award that ran its course. */
    public function selesaikan(BeasiswaPenerima $penerima, ?Staff $staff = null): BeasiswaPenerima
    {
        if ($penerima->status !== StatusPenerima::Aktif) {
            throw new AturanAkademikException('Hanya penerimaan yang masih aktif yang dapat diselesaikan.');
        }

        $penerima->update([
            'status' => StatusPenerima::Selesai,
            'kunci_aktif' => null,
            'diputus_by_staff_id' => $staff?->id,
            'diputus_at' => now(),
        ]);

        return $penerima->refresh();
    }

    /**
     * Pushes a newly granted award onto invoices already raised.
     *
     * The common case in practice: selection finishes weeks after billing, and
     * without this every recipient would be chased for money the campus has
     * already decided not to collect.
     */
    private function terapkanKeTagihan(BeasiswaPenerima $penerima, ?Staff $staff): void
    {
        $penerima->loadMissing(['mulai', 'selesai']);

        Tagihan::query()
            ->with(['item', 'tahunAkademik', 'mahasiswa'])
            ->where('mahasiswa_id', $penerima->mahasiswa_id)
            ->get()
            ->filter(fn (Tagihan $t): bool => $penerima->mencakupTerm($t->tahunAkademik))
            ->each(fn (Tagihan $t) => $this->potongan->terapkan($t, $staff));
    }
}
