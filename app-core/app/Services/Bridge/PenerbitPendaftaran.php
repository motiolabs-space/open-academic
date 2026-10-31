<?php

declare(strict_types=1);

namespace App\Services\Bridge;

use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Support\Facades\DB;

/**
 * Publishes `student.enrolled` — once per student, ever.
 *
 * A student record is born in three places: daftar ulang of an admitted
 * applicant (PmbService, which the PMB transfer intake also goes through), a
 * transfer enrolled without an admissions record (PindahanService), and the
 * new registration of an internal transfer or a readmission
 * (RegistrasiUlangService, which names the earlier one). All call umumkan().
 * Adoption and past-student claims (#44/#44b) link students that already
 * exist here to PDDIKTI; they enrol nobody and publish nothing (ADR-035 §4).
 *
 * Two properties:
 *
 *  - **After commit.** The event is built when the surrounding transaction
 *    commits, from the row as it then stands. The PMB transfer intake
 *    corrects jenis_daftar after daftar ulang inside one transaction; an
 *    event built earlier would announce a transfer as a new intake, and an
 *    event for a rolled-back enrolment would announce a student who does not
 *    exist.
 *  - **Once.** `mahasiswa.bridge_enrolled_at` is claimed with a conditional
 *    update before anything is queued, so a second call — a retry, a second
 *    path, two requests racing — finds it taken and publishes nothing.
 */
class PenerbitPendaftaran
{
    public function __construct(
        private readonly BridgeEventPublisher $bridge,
    ) {}

    /** Schedules the event for when the current transaction commits. */
    public function umumkan(Mahasiswa $mahasiswa): void
    {
        $id = $mahasiswa->getKey();

        DB::afterCommit(fn () => $this->terbitkan($id));
    }

    /**
     * Publishes now, unless this student was announced before.
     *
     * @return bool whether this call was the one that announced them
     */
    public function terbitkan(int $mahasiswaId): bool
    {
        $diklaim = DB::table('mahasiswa')
            ->where('id', $mahasiswaId)
            ->whereNull('bridge_enrolled_at')
            ->update(['bridge_enrolled_at' => now()]);

        if ($diklaim !== 1) {
            return false;
        }

        $mahasiswa = Mahasiswa::query()->with('prodi')->findOrFail($mahasiswaId);

        $this->bridge->publish('student.enrolled', $this->muatan($mahasiswa));

        return true;
    }

    /**
     * The payload documented in docs/openapi/bridge.yaml. Identity and
     * placement only — the same narrow view StudentResource gives, nothing
     * collected for PDDIKTI.
     *
     * @return array<string, mixed>
     */
    public function muatan(Mahasiswa $mahasiswa): array
    {
        return [
            'mahasiswa_uuid' => $mahasiswa->uuid,
            'nim' => $mahasiswa->nim,
            'nama' => $mahasiswa->nama,
            'email' => $mahasiswa->email,
            'prodi' => $mahasiswa->prodi->kode,
            'angkatan' => $mahasiswa->angkatan,
            'semester_masuk' => $mahasiswa->term_masuk,
            'jenis_daftar' => $mahasiswa->jenis_daftar?->value,
            'status' => $mahasiswa->status->value,

            // A re-NIM or readmission opens a new registration for someone
            // already here (ADR-042 §3); this names the earlier one so a
            // consumer can join the two accounts. Null for a first intake.
            'mahasiswa_sebelumnya_uuid' => $mahasiswa->registrasiSebelumnya()->with('mahasiswaLama:id,uuid')->first()?->mahasiswaLama?->uuid,
        ];
    }
}
