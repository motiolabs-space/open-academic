<?php

declare(strict_types=1);

namespace App\Services\Spmi;

use App\Enums\StatusAudit;
use App\Enums\StatusTemuan;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Models\Spmi\AuditMutu;
use App\Models\Spmi\TemuanAudit;
use App\Models\Spmi\TindakLanjutTemuan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Audit Mutu Internal.
 *
 * Three refusals carry this module, and each of them is what separates an audit
 * from a task list:
 *
 *   1. an auditor may not audit their own unit;
 *   2. a finding cannot be edited once closed;
 *   3. a corrective action cannot be verified by whoever carried it out.
 *
 * Accreditation forms are **not** here — they need research, community-service
 * and finance data this application does not own. See docs/KINERJA.md.
 */
class SpmiService
{
    /* ------------------------------------------------------------------
     | Audit
     |----------------------------------------------------------------- */

    /** @param array<string, mixed> $data */
    public function rencanakanAudit(UnitKerja $unit, array $data): AuditMutu
    {
        $this->pastikanSatuAuditor($data);
        $this->pastikanAuditorIndependen($unit, $data);

        return AuditMutu::create([
            ...$data,
            'unit_kerja_id' => $unit->id,
        ]);
    }

    /**
     * Both auditor columns exist because an internal auditor is usually a
     * lecturer but may be quality-assurance staff. Both being set is two
     * answers, not a richer one.
     *
     * @param array<string, mixed> $data
     */
    private function pastikanSatuAuditor(array $data): void
    {
        if (filled($data['auditor_dosen_id'] ?? null) && filled($data['auditor_staff_id'] ?? null)) {
            throw new AturanAkademikException(
                'Pilih satu auditor saja — dari dosen atau dari staf, tidak keduanya.',
            );
        }

        if (blank($data['auditor_dosen_id'] ?? null) && blank($data['auditor_staff_id'] ?? null)) {
            throw new AturanAkademikException('Audit harus punya auditor.');
        }
    }

    /**
     * An auditor may not audit the unit they belong to.
     *
     * The whole instrument depends on this: somebody auditing their own office
     * is reporting on their own work, and a finding they raise against
     * themselves is one they also get to close. Switchable in config because a
     * small campus may genuinely lack auditors — but refusing is the default,
     * and turning it off is a recorded decision rather than an oversight
     * nobody ever sees.
     *
     * @param array<string, mixed> $data
     */
    private function pastikanAuditorIndependen(UnitKerja $unit, array $data): void
    {
        if (!config('spmi.tolak_audit_unit_sendiri', true)) {
            return;
        }

        $unitAuditor = match (true) {
            filled($data['auditor_dosen_id'] ?? null) => Dosen::find($data['auditor_dosen_id'])?->unit_kerja_id,
            filled($data['auditor_staff_id'] ?? null) => Staff::find($data['auditor_staff_id'])?->unit_kerja_id,
            default => null,
        };

        if ($unitAuditor !== null && (int) $unitAuditor === (int) $unit->id) {
            throw new AturanAkademikException(sprintf(
                'Auditor bertugas di unit "%s" dan tidak dapat mengaudit unitnya sendiri.',
                $unit->nama,
            ));
        }
    }

    public function mulaiAudit(AuditMutu $audit): AuditMutu
    {
        if ($audit->status !== StatusAudit::Direncanakan) {
            throw new AturanAkademikException('Hanya audit berstatus direncanakan yang dapat dimulai.');
        }

        $audit->update(['status' => StatusAudit::Berlangsung]);

        return $audit->refresh();
    }

    /**
     * Closes the audit itself. Findings inside it keep their own life.
     *
     * A finding usually outlives the audit that raised it — the corrective
     * action runs for weeks afterwards — so closing the audit does not close
     * them. Forcing that would either falsify the record or block the auditor
     * from ever finishing.
     */
    public function tutupAudit(AuditMutu $audit, ?string $ringkasan = null): AuditMutu
    {
        if ($audit->status === StatusAudit::Selesai) {
            throw new AturanAkademikException('Audit ini sudah ditutup.');
        }

        $audit->update([
            'status' => StatusAudit::Selesai,
            'ringkasan' => $ringkasan ?? $audit->ringkasan,
            'ditutup_at' => now(),
        ]);

        return $audit->refresh();
    }

    /* ------------------------------------------------------------------
     | Temuan
     |----------------------------------------------------------------- */

    /** @param array<string, mixed> $data */
    public function catatTemuan(AuditMutu $audit, array $data): TemuanAudit
    {
        if (!$audit->status->menerimaTemuan()) {
            throw new AturanAkademikException(sprintf(
                'Audit berstatus %s tidak menerima temuan baru.',
                $audit->status->label(),
            ));
        }

        $jenis = (string) $data['jenis'];
        $definisi = config('spmi.jenis_temuan')[$jenis] ?? null;

        if ($definisi === null) {
            throw new AturanAkademikException(sprintf(
                'Jenis temuan "%s" tidak dikenal. Yang tersedia: %s.',
                $jenis,
                implode(', ', array_keys((array) config('spmi.jenis_temuan'))),
            ));
        }

        /*
         * The deadline comes from the finding's severity rather than from the
         * form. A major non-conformity given ninety days by whoever typed it is
         * the campus quietly re-grading its own rule.
         */
        $tenggat = $definisi['tenggat_hari'] === null
            ? null
            : now()->addDays((int) $definisi['tenggat_hari'])->toDateString();

        return TemuanAudit::create([
            ...$data,
            'audit_mutu_id' => $audit->id,
            'tenggat' => $tenggat,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function perbaruiTemuan(TemuanAudit $temuan, array $data): TemuanAudit
    {
        $this->pastikanTemuanTerbuka($temuan);

        $temuan->fill($data)->save();

        return $temuan->refresh();
    }

    /**
     * Closes a finding, but only once it has actually been corrected.
     *
     * For the kinds that require it, closing without a verified corrective
     * action is how an audit becomes paperwork: the finding disappears from the
     * list and nothing changed in the unit.
     */
    public function tutupTemuan(TemuanAudit $temuan, Staff $staff): TemuanAudit
    {
        $this->pastikanTemuanTerbuka($temuan);

        if ($temuan->wajibTindakLanjut()) {
            $terverifikasi = $temuan->tindakLanjut()->where('is_terverifikasi', true)->exists();

            if (!$terverifikasi) {
                throw new AturanAkademikException(sprintf(
                    '%s hanya dapat ditutup setelah ada tindak lanjut yang terverifikasi.',
                    $temuan->jenisLabel(),
                ));
            }
        }

        $temuan->update([
            'status' => StatusTemuan::Ditutup,
            'ditutup_at' => now(),
            'ditutup_by_staff_id' => $staff->id,
        ]);

        return $temuan->refresh();
    }

    private function pastikanTemuanTerbuka(TemuanAudit $temuan): void
    {
        if (!$temuan->status->dapatDiubah()) {
            throw new AturanAkademikException(
                'Temuan yang sudah ditutup tidak dapat diubah lagi. '
                    .'Catat temuan baru pada audit berikutnya bila persoalannya kembali.',
            );
        }
    }

    /* ------------------------------------------------------------------
     | Tindak lanjut
     |----------------------------------------------------------------- */

    /** @param array<string, mixed> $data */
    public function catatTindakLanjut(TemuanAudit $temuan, array $data, ?Staff $staff = null): TindakLanjutTemuan
    {
        $this->pastikanTemuanTerbuka($temuan);

        return DB::transaction(function () use ($temuan, $data, $staff): TindakLanjutTemuan {
            $tindak = TindakLanjutTemuan::create([
                ...$data,
                'temuan_audit_id' => $temuan->id,
                'dicatat_by_staff_id' => $staff?->id,
            ]);

            if ($temuan->status === StatusTemuan::Terbuka) {
                $temuan->update(['status' => StatusTemuan::Ditindaklanjuti]);
            }

            return $tindak;
        });
    }

    /**
     * Verifies a corrective action — by somebody other than its author.
     *
     * A correction verified by whoever carried it out is not verification; it
     * is a second statement from the same person, and the audit trail cannot
     * tell the two apart afterwards.
     */
    public function verifikasiTindakLanjut(
        TindakLanjutTemuan $tindak,
        Staff $staff,
        ?string $catatan = null,
    ): TindakLanjutTemuan {
        if ($tindak->is_terverifikasi) {
            throw new AturanAkademikException('Tindak lanjut ini sudah diverifikasi.');
        }

        if ($tindak->dicatat_by_staff_id !== null && (int) $tindak->dicatat_by_staff_id === (int) $staff->id) {
            throw new AturanAkademikException(
                'Tindak lanjut tidak dapat diverifikasi oleh orang yang mencatatnya sendiri.',
            );
        }

        if (blank($tindak->realisasi)) {
            throw new AturanAkademikException(
                'Tindak lanjut belum punya realisasi untuk diverifikasi.',
            );
        }

        $tindak->update([
            'is_terverifikasi' => true,
            'diverifikasi_by_staff_id' => $staff->id,
            'diverifikasi_at' => now(),
            'catatan_verifikasi' => $catatan,
        ]);

        return $tindak->refresh();
    }

    /* ------------------------------------------------------------------
     | Baca
     |----------------------------------------------------------------- */

    /**
     * Open findings, worst and latest first.
     *
     * @return Collection<int, TemuanAudit>
     */
    public function temuanTerbuka(?int $tahun = null): Collection
    {
        return TemuanAudit::query()
            ->with(['audit.unit', 'standar', 'tindakLanjut'])
            ->terbuka()
            ->when($tahun, fn ($q) => $q->whereHas('audit', fn ($a) => $a->where('tahun', $tahun)))
            ->orderBy('tenggat')
            ->get();
    }

    /**
     * @return array{mayor: int, minor: int, observasi: int, saran: int, terlambat: int}
     */
    public function rekapTemuan(?int $tahun = null): array
    {
        return $this->rekapDari($this->temuanTerbuka($tahun));
    }

    /**
     * Counts a set of findings already in hand.
     *
     * Split from `rekapTemuan()` because the screen needs both the list and its
     * tally, and asking twice runs the same query twice — the duplication that
     * cost `/dosen/rps` twenty queries before it was noticed.
     *
     * @param Collection<int, TemuanAudit> $temuan
     * @return array{mayor: int, minor: int, observasi: int, saran: int, terlambat: int}
     */
    public function rekapDari(Collection $temuan): array
    {
        return [
            'mayor' => $temuan->where('jenis', 'mayor')->count(),
            'minor' => $temuan->where('jenis', 'minor')->count(),
            'observasi' => $temuan->where('jenis', 'observasi')->count(),
            'saran' => $temuan->where('jenis', 'saran')->count(),
            'terlambat' => $temuan->filter(fn (TemuanAudit $t): bool => $t->terlambat())->count(),
        ];
    }
}
