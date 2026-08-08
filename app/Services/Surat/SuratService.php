<?php

declare(strict_types=1);

namespace App\Services\Surat;

use App\Enums\JenisSurat;
use App\Enums\StatusSurat;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use App\Models\Surat\Surat;
use App\Notifications\Surat\SuratTerbit;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Requesting, issuing, and withdrawing official letters.
 *
 * Two ideas run through everything here.
 *
 * **The system already knows the answer to most of these.** A certificate of
 * enrolment is the campus reading a status column out loud; there is no
 * judgement in it, and the queue at the counter exists only because nobody
 * wired the column to a printer. So types that assert a fact are issued on the
 * spot, and only the ones that commit the institution to somebody else's
 * project wait for a person.
 *
 * **A letter says what was true when it was written.** The facts are frozen
 * into the record at the moment of issue and never reassembled afterwards. A
 * letter certifying an active student stays a truthful account of March even
 * after the student leaves in April — and verification is what tells a reader
 * which of those two they are looking at.
 */
class SuratService
{
    public function __construct(
        private readonly PenomoranSurat $penomoran,
        private readonly PerakitKonten $perakit,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Files a request, and issues it immediately when nobody needs to decide.
     */
    public function ajukan(Mahasiswa $mahasiswa, JenisSurat $jenis, ?string $keperluan = null): Surat
    {
        if ($jenis === JenisSurat::Skpi) {
            throw new AturanAkademikException(
                'Surat Keterangan Pendamping Ijazah diterbitkan bersama kelulusan, bukan atas permintaan.',
            );
        }

        if ($jenis->perluKeperluan() && blank($keperluan)) {
            throw new AturanAkademikException(
                'Surat pengantar wajib menyebutkan keperluannya — kalimat itulah isi suratnya.',
            );
        }

        $halangan = $this->halangan($mahasiswa, $jenis);

        if ($halangan !== null) {
            throw new AturanAkademikException($halangan);
        }

        $surat = Surat::create([
            'mahasiswa_id' => $mahasiswa->id,
            'jenis' => $jenis,
            'status' => StatusSurat::Diajukan,
            'keperluan' => $keperluan,
            'diajukan_at' => now(),
        ]);

        return $jenis->swalayan()
            ? $this->terbitkan($surat, null)
            : $surat;
    }

    /**
     * Issues the letter: freezes the facts, claims a number, notifies.
     *
     * `$staff` is null for the self-service path. The column stays empty rather
     * than being filled with whoever happened to be logged in — a signature
     * block naming a person who never saw the request is worse than one naming
     * the office.
     */
    public function terbitkan(Surat $surat, ?Staff $staff): Surat
    {
        if ($surat->status !== StatusSurat::Diajukan) {
            throw new AturanAkademikException(
                'Hanya permohonan yang masih menunggu yang dapat diterbitkan. Status saat ini: '
                    .$surat->status->label().'.',
            );
        }

        // Re-checked here, not trusted from the request. Weeks can pass between
        // asking and issuing, and a student who has since taken leave must not
        // receive a letter saying they are enrolled.
        $halangan = $this->halangan($surat->mahasiswa, $surat->jenis);

        if ($halangan !== null) {
            throw new AturanAkademikException('Syarat tidak lagi terpenuhi: '.$halangan);
        }

        $masa = $surat->jenis->masaBerlakuHari();

        DB::transaction(function () use ($surat, $staff, $masa): void {
            $surat->forceFill([
                'status' => StatusSurat::Diterbitkan,
                'konten' => $this->perakit->untuk($surat),
                'berlaku_sampai' => $masa !== null ? now()->addDays($masa)->toDateString() : null,
                'diterbitkan_at' => now(),
                'diterbitkan_by_staff_id' => $staff?->id,
            ])->save();

            $this->penomoran->bubuhkan($surat);
        });

        $this->notifier->kirim($surat->mahasiswa, new SuratTerbit($surat->refresh()));

        return $surat->refresh();
    }

    public function tolak(Surat $surat, Staff $staff, string $alasan): Surat
    {
        if ($surat->status !== StatusSurat::Diajukan) {
            throw new AturanAkademikException(
                'Hanya permohonan yang masih menunggu yang dapat ditolak.',
            );
        }

        if (blank($alasan)) {
            throw new AturanAkademikException(
                'Penolakan wajib disertai alasan yang dapat dibaca pemohon.',
            );
        }

        // No number is consumed. A rejected request must not leave a gap in the
        // sequence for somebody to explain later.
        $surat->update([
            'status' => StatusSurat::Ditolak,
            'alasan' => $alasan,
            'diterbitkan_by_staff_id' => $staff->id,
        ]);

        $this->notifier->kirim($surat->mahasiswa, new SuratTerbit($surat->refresh()));

        return $surat->refresh();
    }

    /**
     * Withdraws a letter that should not stand.
     *
     * The row survives, and so does its number. Somebody is holding the paper;
     * verification must be able to say "this is genuine and the campus has
     * withdrawn it" rather than "no such document", which reads as a forgery
     * and puts the holder in the wrong.
     */
    public function cabut(Surat $surat, Staff $staff, string $alasan): Surat
    {
        if ($surat->status !== StatusSurat::Diterbitkan) {
            throw new AturanAkademikException('Hanya surat yang sudah terbit yang dapat dicabut.');
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Pencabutan surat wajib disertai alasan.');
        }

        DB::transaction(function () use ($surat, $staff, $alasan): void {
            $surat->update([
                'status' => StatusSurat::Dicabut,
                'dicabut_at' => now(),
                'alasan' => $alasan,
            ]);

            $surat->recordActivity('revoked', sprintf(
                'Surat %s dicabut oleh %s. Alasan: %s',
                $surat->nomor,
                $staff->nama,
                $alasan,
            ));
        });

        return $surat->refresh();
    }

    /**
     * Issues a diploma supplement. Called when graduation is confirmed.
     *
     * Idempotent: a graduate has one supplement, and re-running graduation
     * confirmation must not produce a second with a different number.
     */
    public function terbitkanSkpi(Mahasiswa $mahasiswa, ?Staff $staff = null): ?Surat
    {
        $ada = Surat::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('jenis', JenisSurat::Skpi->value)
            ->whereIn('status', [StatusSurat::Diterbitkan->value, StatusSurat::Diajukan->value])
            ->first();

        if ($ada !== null) {
            return $ada;
        }

        if ($this->halangan($mahasiswa, JenisSurat::Skpi) !== null) {
            return null;
        }

        return $this->terbitkan(Surat::create([
            'mahasiswa_id' => $mahasiswa->id,
            'jenis' => JenisSurat::Skpi,
            'status' => StatusSurat::Diajukan,
            'diajukan_at' => now(),
        ]), $staff);
    }

    /**
     * Why this person cannot have this letter, or null when they can.
     *
     * One method rather than a check scattered across the request path and the
     * issue path — those two run weeks apart and would drift.
     */
    public function halangan(Mahasiswa $mahasiswa, JenisSurat $jenis): ?string
    {
        return match ($jenis) {
            JenisSurat::AktifKuliah => $this->halanganAktif($mahasiswa),
            JenisSurat::Pengantar => $mahasiswa->status === StudentStatus::Aktif
                ? null
                : 'surat pengantar hanya untuk mahasiswa aktif; status saat ini '
                    .$mahasiswa->status->label().'.',
            JenisSurat::KeteranganLulus, JenisSurat::Skpi => $this->halanganLulus($mahasiswa),
            JenisSurat::TranskripLegalisir => $mahasiswa->nilai()->where('is_final', true)->exists()
                ? null
                : 'belum ada nilai yang difinalisasi, sehingga tidak ada yang dapat dilegalisir.',
        };
    }

    private function halanganAktif(Mahasiswa $mahasiswa): ?string
    {
        if ($mahasiswa->status !== StudentStatus::Aktif) {
            return 'surat ini menyatakan mahasiswa sedang aktif; status saat ini '
                .$mahasiswa->status->label().'.';
        }

        if (!config('surat.syarat.tahan_bila_menunggak')) {
            return null;
        }

        /*
         * Only invoices already past their due date count.
         *
         * Blocking on any unpaid balance would refuse the letter to everyone in
         * the first week of a term, including the students who need it to
         * release the scholarship that pays the invoice.
         */
        $tunggakan = Tagihan::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->belumLunas()
            ->whereDate('jatuh_tempo', '<', now()->toDateString())
            ->exists();

        return $tunggakan
            ? 'terdapat tagihan yang sudah melewati jatuh tempo.'
            : null;
    }

    private function halanganLulus(Mahasiswa $mahasiswa): ?string
    {
        $yudisium = $mahasiswa->yudisium;

        return $yudisium !== null && $yudisium->status === 'ditetapkan'
            ? null
            : 'kelulusan belum ditetapkan.';
    }
}
