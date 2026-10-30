<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\DTOs\Sdm\KegiatanDosenData;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\Staff;
use App\Services\Berkas\BerkasService;
use App\Services\Bridge\BridgeEventPublisher;
use Illuminate\Http\UploadedFile;

/**
 * A lecturer's activity, from claim to evidence.
 *
 * The lecturer records it on their portfolio; a named staff member verifies
 * it on the IKU data screen; only then does Campus Bridge hear about it. The
 * two halves live together so the rule between them — nothing arrives
 * verified — is enforced in one place.
 */
class PenugasanDosenService
{
    public function __construct(
        private readonly BerkasService $berkas,
        private readonly BridgeEventPublisher $bridge,
    ) {}

    public function catat(
        Dosen $dosen,
        TahunAkademik $term,
        KegiatanDosenData $data,
        ?UploadedFile $dokumen = null,
    ): PenugasanDosen {
        return PenugasanDosen::create([
            'dosen_id' => $dosen->id,
            'tahun_akademik_id' => $term->id,
            ...$data->kolom(),
            'dokumen_path' => $dokumen !== null
                ? $this->berkas->simpan($dokumen, 'portofolio/'.$dosen->uuid)
                : null,

            /*
             * Never verified on creation, whoever is signed in.
             *
             * This row becomes a BKD line and IKU evidence. A self-reported
             * activity that arrives pre-verified is an indicator resting on an
             * unchecked claim, which is the one thing the verification screen
             * exists to prevent.
             */
            'is_verified' => false,
        ]);
    }

    /** Marks the row checked by a named staff member and tells Open Campus. */
    public function verifikasi(PenugasanDosen $penugasan, Staff $staf): PenugasanDosen
    {
        $penugasan->update([
            'is_verified' => true,
            'verified_by_staff_id' => $staf->id,
            'verified_at' => now(),
        ]);

        $this->bridge->publish('lecturer.assignment_recorded', [
            'penugasan_uuid' => $penugasan->uuid,
            'dosen_uuid' => $penugasan->dosen->uuid,
            'nidn' => $penugasan->dosen->nidn,
            'semester' => $penugasan->tahunAkademik->kode,
            'jenis' => $penugasan->jenis->value,
            'judul' => $penugasan->judul,
            'mitra' => $penugasan->mitra_nama,

            // Which indicator this record feeds under the older numbering,
            // decided by the enum rather than left for the consumer to infer
            // from a free-text type. Frozen with the legacy keys (ADR-036).
            'iku' => array_values(array_filter([
                $penugasan->jenis->countsForIku3() ? 3 : null,
                $penugasan->jenis->countsForIku4() ? 4 : null,
            ])),
        ]);

        return $penugasan;
    }

    public function cabutVerifikasi(PenugasanDosen $penugasan): PenugasanDosen
    {
        $penugasan->update(['is_verified' => false, 'verified_by_staff_id' => null, 'verified_at' => null]);

        return $penugasan;
    }
}
