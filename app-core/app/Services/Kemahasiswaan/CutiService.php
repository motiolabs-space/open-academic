<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\AjuanCutiData;
use App\Enums\KrsStatus;
use App\Enums\LeaveStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\CutiDiputus;
use App\Services\Berkas\BerkasService;
use App\Services\Bridge\BridgeEventPublisher;
use App\Services\Notifikasi\Notifier;
use App\Support\Portal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Academic leave.
 *
 * Leave is not a note on a file — it changes the student's reported status,
 * suspends the semester counter, and is the difference between "cuti" and
 * "dropped out" in the eyes of PDDIKTI. A student marked active while actually
 * away accrues a semester they did not study; one marked on leave while
 * actually studying loses one they did.
 */
class CutiService
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly BerkasService $berkas,
        private readonly BridgeEventPublisher $bridge,
    ) {}

    /**
     * The terms a student may apply for from the portal: the active one and
     * the regular term after it (ADR-040 §1).
     *
     * Short terms are left out — nobody takes leave from an optional term —
     * and so is anything further ahead, because leave decided a year early is
     * decided on circumstances that will have changed. Staff at the counter
     * are not limited to these.
     *
     * @return Collection<int, TahunAkademik>
     */
    public function semesterAjuanMandiri(): Collection
    {
        $aktif = Portal::term();

        if ($aktif === null) {
            return collect();
        }

        $berikut = TahunAkademik::query()
            ->where('kode', '>', $aktif->kode)
            ->where('semester', '!=', SemesterType::Antara->value)
            ->orderBy('kode')
            ->first();

        return collect([$aktif, $berikut])
            ->filter(fn (?TahunAkademik $t): bool => $t !== null && $t->semester !== SemesterType::Antara)
            ->values();
    }

    /**
     * A student applying for themselves.
     *
     * Every rule of ajukan() applies unchanged; this adds only the term window
     * above and the supporting document, stored inside the same transaction so
     * an application never exists without the file the student attached.
     */
    public function ajukanMandiri(Mahasiswa $mahasiswa, AjuanCutiData $data): CutiMahasiswa
    {
        $term = $this->semesterAjuanMandiri()->firstWhere('id', $data->tahunAkademikId);

        if ($term === null) {
            throw new AturanAkademikException(__('swalayan.cuti.galat.semester_tidak_dibuka'));
        }

        return DB::transaction(function () use ($mahasiswa, $term, $data): CutiMahasiswa {
            $cuti = $this->ajukan($mahasiswa, $term, $data->jenis->value, $data->alasan);

            if ($data->dokumen !== null) {
                $cuti->update(['dokumen_path' => $this->berkas->simpan($data->dokumen, 'cuti/'.$mahasiswa->uuid)]);
            }

            $cuti->recordActivity('leave_requested', "Cuti {$data->jenis->value} untuk {$term->nama} diajukan mandiri oleh mahasiswa.");

            return $cuti->refresh();
        });
    }

    /**
     * Files a leave application.
     *
     * Refused while a study plan is live for the same term: a student cannot be
     * both enrolled in courses and away from campus, and a leave granted on top
     * of an approved KRS leaves classes with a registered student who will
     * never attend.
     */
    public function ajukan(
        Mahasiswa $mahasiswa,
        TahunAkademik $term,
        string $jenis,
        string $alasan,
    ): CutiMahasiswa {
        if ($mahasiswa->status !== StudentStatus::Aktif) {
            throw new AturanAkademikException(
                'Hanya mahasiswa berstatus aktif yang dapat mengajukan cuti. Status saat ini: '
                    .$mahasiswa->status->label().'.',
            );
        }

        if (CutiMahasiswa::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $term->id)
            ->exists()
        ) {
            throw new AturanAkademikException("Pengajuan cuti untuk {$term->nama} sudah pernah dibuat.");
        }

        $krs = $mahasiswa->krs()
            ->where('tahun_akademik_id', $term->id)
            ->whereIn('status', [KrsStatus::Diajukan->value, KrsStatus::Disetujui->value])
            ->exists();

        if ($krs) {
            throw new AturanAkademikException(
                "Mahasiswa ini masih memiliki KRS aktif pada {$term->nama}. "
                    .'Batalkan rencana studinya lebih dulu — seseorang tidak dapat sekaligus '
                    .'mengambil kelas dan sedang cuti.',
            );
        }

        return CutiMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'jenis' => $jenis,
            'alasan' => $alasan,
            'status' => LeaveStatus::Diajukan,
            'diajukan_at' => now(),
        ]);
    }

    /**
     * Grants leave and moves the student's status with it.
     *
     * Both writes in one transaction: an approved application with the student
     * still marked active is exactly the inconsistency that gets reported to
     * PDDIKTI and has to be corrected by hand later.
     */
    public function setujui(CutiMahasiswa $cuti, Staff $staff, ?string $catatan = null): CutiMahasiswa
    {
        $this->pastikanBelumDiputus($cuti);

        $statusLama = $cuti->mahasiswa->status;

        DB::transaction(function () use ($cuti, $staff, $catatan): void {
            $cuti->update([
                'status' => LeaveStatus::Disetujui,
                'diproses_by_staff_id' => $staff->id,
                'diproses_at' => now(),
                'catatan' => $catatan,
            ]);

            $cuti->mahasiswa->update(['status' => StudentStatus::Cuti]);
            $cuti->mahasiswa->recordActivity(
                'leave_granted',
                "Cuti {$cuti->jenis} disetujui untuk {$cuti->tahunAkademik->nama}.",
            );
        });

        $this->umumkanStatus($cuti->mahasiswa, $statusLama, StudentStatus::Cuti);

        // A student who assumes leave was granted, and it was not, accrues a
        // semester they did not study. Both outcomes are told.
        $this->notifier->kirim($cuti->mahasiswa, new CutiDiputus($cuti->refresh()));

        return $cuti->refresh();
    }

    public function tolak(CutiMahasiswa $cuti, Staff $staff, string $catatan): CutiMahasiswa
    {
        $this->pastikanBelumDiputus($cuti);

        if (blank($catatan)) {
            throw new AturanAkademikException('Penolakan cuti wajib disertai alasan yang dapat dibaca mahasiswa.');
        }

        $cuti->update([
            'status' => LeaveStatus::Ditolak,
            'diproses_by_staff_id' => $staff->id,
            'diproses_at' => now(),
            'catatan' => $catatan,
        ]);

        $this->notifier->kirim($cuti->mahasiswa, new CutiDiputus($cuti->refresh()));

        return $cuti->refresh();
    }

    /**
     * Brings a student back from leave.
     *
     * The reverse of setujui(), and just as consequential: a student who is
     * back but still marked on leave cannot fill a study plan, and no error
     * will explain why.
     */
    public function aktifkanKembali(CutiMahasiswa $cuti, Staff $staff): CutiMahasiswa
    {
        if ($cuti->status !== LeaveStatus::Disetujui) {
            throw new AturanAkademikException('Hanya cuti yang sedang berjalan yang dapat diakhiri.');
        }

        DB::transaction(function () use ($cuti, $staff): void {
            $cuti->update([
                'status' => LeaveStatus::Dibatalkan,
                'diproses_by_staff_id' => $staff->id,
                'diproses_at' => now(),
            ]);

            $cuti->mahasiswa->update(['status' => StudentStatus::Aktif]);
            $cuti->mahasiswa->recordActivity('leave_ended', 'Mahasiswa aktif kembali dari cuti.');
        });

        $this->umumkanStatus($cuti->mahasiswa, StudentStatus::Cuti, StudentStatus::Aktif);

        return $cuti->refresh();
    }

    /**
     * Tells Open Campus the status moved — the same event and shape
     * YudisiumService publishes. Outside the transaction: a webhook queued for
     * a decision that rolled back would announce something that never happened.
     */
    private function umumkanStatus(Mahasiswa $mahasiswa, StudentStatus $lama, StudentStatus $baru): void
    {
        $this->bridge->publish('student.status_changed', [
            'mahasiswa_uuid' => $mahasiswa->uuid,
            'nim' => $mahasiswa->nim,
            'status_lama' => $lama->value,
            'status_baru' => $baru->value,
        ]);
    }

    private function pastikanBelumDiputus(CutiMahasiswa $cuti): void
    {
        if (in_array($cuti->status, [LeaveStatus::Disetujui, LeaveStatus::Ditolak], true)) {
            throw new AturanAkademikException(
                'Pengajuan cuti ini sudah diputus ('.$cuti->status->label().').',
            );
        }
    }
}
