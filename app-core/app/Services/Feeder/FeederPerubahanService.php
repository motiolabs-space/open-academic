<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\FeederSyncStatus;
use App\Enums\JalurPerubahanFeeder;
use App\Enums\JenisPerubahanFeeder;
use App\Enums\StatusPerubahanFeeder;
use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederChangeRequest;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\Mappers\RiwayatPendidikanMapper;

/**
 * The correction path for what PDDIKTI will not let a sync overwrite.
 *
 * Once a student's biodata and exit record are accepted, PDDIKTI freezes
 * the identity and exit columns. A campus that edits one of them here has
 * three honest options and one dishonest one: file the change at PDDIKTI
 * admin, send it through the web service where that is allowed, leave the
 * two sides knowingly different — or re-push the biodata and let the
 * refusal land in the ledger as one more failed row. This service exists so
 * the fourth never happens by accident: the change is recorded as a request
 * the moment it is made, the validator refuses the biodata while it is open,
 * and the console says which form to fill in.
 */
class FeederPerubahanService
{
    public const ACT_RIWAYAT = 'UpdateRiwayatPendidikanMahasiswa';

    public function __construct(
        private readonly FeederClientInterface $client,
        private readonly RiwayatPendidikanMapper $riwayat,
    ) {}

    /**
     * Records that a locked column changed, or amends the open request for it.
     *
     * One open request per student and column. A second edit to the same
     * column before the first is processed updates the requested value; an
     * edit that puts the original value back withdraws the request, because
     * PDDIKTI now agrees with us again and there is nothing to file.
     */
    public function catat(
        Mahasiswa $mahasiswa,
        string $kolom,
        JenisPerubahanFeeder $jenis,
        JalurPerubahanFeeder $jalur,
        ?string $lama,
        ?string $baru,
    ): ?FeederChangeRequest {
        $terbuka = FeederChangeRequest::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('kolom', $kolom)
            ->terbuka()
            ->latest('id')
            ->first();

        if ($terbuka !== null) {
            if ($terbuka->nilai_lama === $baru) {
                $terbuka->delete();

                return null;
            }

            $terbuka->update(['nilai_baru' => $baru]);

            return $terbuka;
        }

        if ($lama === $baru) {
            return null;
        }

        return FeederChangeRequest::create([
            'mahasiswa_id' => $mahasiswa->id,
            'jenis' => $jenis,
            'kolom' => $kolom,
            'nilai_lama' => $lama,
            'nilai_baru' => $baru,
            'jalur' => $jalur,
            'status' => StatusPerubahanFeeder::Menunggu,
        ]);
    }

    /** Marks a request as filed at PDDIKTI admin, with the letter that carries it. */
    public function ajukan(FeederChangeRequest $perubahan, Staff $staff, ?string $nomorSurat = null, ?string $catatan = null): FeederChangeRequest
    {
        $perubahan->update([
            'status' => StatusPerubahanFeeder::Diajukan,
            'nomor_surat' => $nomorSurat ?? $perubahan->nomor_surat,
            'catatan' => $catatan ?? $perubahan->catatan,
            'diajukan_at' => $perubahan->diajukan_at ?? now(),
            'diproses_by_staff_id' => $staff->id,
        ]);

        return $perubahan;
    }

    /**
     * Marks a request as done: PDDIKTI now holds the new value.
     *
     * This is the operator's statement, made after checking PDDIKTI admin,
     * and it is what reopens the student's biodata for syncing. Nothing here
     * verifies it — the web service offers no way to read a PDM decision.
     */
    public function selesaikan(FeederChangeRequest $perubahan, Staff $staff): FeederChangeRequest
    {
        $perubahan->update([
            'status' => StatusPerubahanFeeder::Selesai,
            'selesai_at' => now(),
            'diproses_by_staff_id' => $staff->id,
        ]);

        return $perubahan;
    }

    /**
     * Sends a web-service-eligible request through
     * UpdateRiwayatPendidikanMahasiswa, keyed on the registration PDDIKTI
     * already knows.
     *
     * The whole enrolment record travels, not just the changed column: that
     * is the act's contract. The call is written to the ledger like any push,
     * and a refusal stays on the request — a NIM change refused because the
     * reporting period closed is one the operator files at PDDIKTI admin
     * instead, and the error text is what goes in the letter.
     */
    public function kirim(FeederChangeRequest $perubahan, Staff $staff, TahunAkademik $term): FeederChangeRequest
    {
        if (!config('feeder.enabled')) {
            throw FeederException::dinonaktifkan();
        }

        if ($perubahan->jalur !== JalurPerubahanFeeder::WebService) {
            throw FeederException::hanyaLewatPddiktiAdmin($perubahan->labelKolom());
        }

        $mahasiswa = $perubahan->mahasiswa()->with('prodi')->firstOrFail();

        if (blank($mahasiswa->feeder_registrasi_id)) {
            throw FeederException::registrasiBelumSinkron($mahasiswa->nim);
        }

        $kunci = ['id_registrasi_mahasiswa' => $mahasiswa->feeder_registrasi_id];
        $payload = $this->riwayat->payload($mahasiswa);

        try {
            $response = $this->client->update(self::ACT_RIWAYAT, $kunci, $payload);
            $gagal = $response->gagal();
            $kode = (string) $response->errorCode;
            $pesan = $response->errorDesc;
        } catch (FeederException $e) {
            $gagal = true;
            $kode = (string) ($e->feederErrorCode ?? 'transport');
            $pesan = $e->getMessage();
        }

        FeederSyncLog::create([
            'entity' => 'riwayat_pendidikan',
            'action' => self::ACT_RIWAYAT,
            'direction' => 'push',
            'local_type' => $mahasiswa->getMorphClass(),
            'local_id' => $mahasiswa->getKey(),
            'feeder_id' => $mahasiswa->feeder_registrasi_id,
            'tahun_akademik_id' => $term->id,
            'payload_hash' => FeederSyncLog::hashPayload($payload),
            'payload' => $payload,
            'status' => $gagal ? FeederSyncStatus::Failed : FeederSyncStatus::Success,
            'error_code' => $gagal ? $kode : null,
            'error_message' => $gagal ? $pesan : null,
            'attempts' => 1,
            'synced_at' => $gagal ? null : now(),
        ]);

        $perubahan->update($gagal
            ? ['error_message' => $pesan, 'diproses_by_staff_id' => $staff->id]
            : [
                'status' => StatusPerubahanFeeder::Selesai,
                'dikirim_at' => now(),
                'selesai_at' => now(),
                'error_message' => null,
                'diproses_by_staff_id' => $staff->id,
            ]);

        return $perubahan;
    }
}
