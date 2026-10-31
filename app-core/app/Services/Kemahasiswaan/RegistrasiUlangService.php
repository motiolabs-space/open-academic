<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\RegistrasiUlangData;
use App\Enums\JenisRegistrasiUlang;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\RiwayatRegistrasiMahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Akademik\KonversiService;
use App\Services\Branding\BrandingService;
use App\Services\Bridge\BridgeEventPublisher;
use App\Services\Bridge\PenerbitPendaftaran;
use App\Services\Pmb\NimGenerator;
use App\Services\Pmb\PmbService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Re-NIM, readmission and reactivation (ROADMAP #55, ADR-042 §3).
 *
 * PDDIKTI keys everything on a registration: one person, one programme, one
 * NIM. A student who moves to another programme here, or comes back after
 * leaving, gets a new registration there — a new id_registrasi_mahasiswa
 * under the same id_mahasiswa — and so a new student row here, linked to the
 * old one by a riwayat_registrasi_mahasiswa row. The old row keeps its NIM,
 * its grades and its PDDIKTI registration. Overwriting the NIM in place would
 * re-key every past KRS and grade line onto the new registration the next
 * time an old term is synced.
 *
 * What reaches Feeder, through the existing mappers and nothing new:
 *
 *  - the new row inherits the person's `feeder_id`, so no second biodata is
 *    inserted; its enrolment record goes out through
 *    InsertRiwayatPendidikanMahasiswa with the mapped jenis daftar and this
 *    campus as the origin;
 *  - an internal transfer closes the old registration: status Ganti Prodi,
 *    sent as Lulus/DO with the mapped jenis keluar in the event's term;
 *  - reactivation reopens the same registration. When its exit was already
 *    reported, the status change opens a PJK request through the observer
 *    that watches every locked column (ADR-017) — the exit is annulled at
 *    PDDIKTI admin, never by a delete from here.
 */
class RegistrasiUlangService
{
    /** Personal columns a new registration of the same person starts with. */
    private const KOLOM_PRIBADI = [
        'nama', 'nik', 'nisn', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama_kode', 'telepon',
        'email_pribadi', 'alamat', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos',
        'nama_ayah', 'nama_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'penghasilan_ortu',
        'asal_sekolah', 'tahun_lulus_sekolah', 'foto_path',
    ];

    public function __construct(
        private readonly NimGenerator $nim,
        private readonly PmbService $pmb,
        private readonly KonversiService $konversi,
        private readonly PenerbitPendaftaran $pendaftaran,
        private readonly BridgeEventPublisher $bridge,
        private readonly BrandingService $branding,
    ) {}

    /**
     * Records the event and carries it out, all or nothing.
     *
     * @return array{riwayat: RiwayatRegistrasiMahasiswa, mahasiswa: Mahasiswa, kata_sandi: ?string}
     *                                                                                               the event, the registration that is now current, and the new account's
     *                                                                                               temporary password when one was created
     */
    public function catat(Mahasiswa $lama, RegistrasiUlangData $data, ?Staff $staff): array
    {
        $this->pastikanBoleh($lama, $data);

        $statusSebelum = $lama->status;
        $kataSandi = $data->jenis->registrasiBaru() ? $this->pmb->kataSandiSementara() : null;

        $hasil = DB::transaction(function () use ($lama, $data, $staff, $statusSebelum, $kataSandi): array {
            // Locked so two registrars recording the same student at once
            // cannot both pass the "not already transferred" check.
            $lama = Mahasiswa::query()->with('prodi')->lockForUpdate()->findOrFail($lama->id);
            $this->pastikanBelumDialihkan($lama, $data);

            $baru = $data->jenis->registrasiBaru() ? $this->bukaRegistrasi($lama, $data, (string) $kataSandi) : null;

            $statusSesudah = match ($data->jenis) {
                JenisRegistrasiUlang::PindahProdi => StudentStatus::GantiProdi,
                JenisRegistrasiUlang::AktifKembali => StudentStatus::Aktif,
                JenisRegistrasiUlang::MasukKembali => $statusSebelum,
            };

            if ($statusSesudah !== $statusSebelum) {
                $lama->update(['status' => $statusSesudah]);
            }

            $riwayat = RiwayatRegistrasiMahasiswa::create([
                'jenis' => $data->jenis,
                'mahasiswa_lama_id' => $lama->id,
                'mahasiswa_baru_id' => $baru?->id,
                'tahun_akademik_id' => $data->term->id,
                'tanggal' => $data->tanggal->toDateString(),
                'nim_lama' => $lama->nim,
                'nim_baru' => $baru?->nim,
                'prodi_lama_id' => $lama->prodi_id,
                'prodi_baru_id' => $baru?->prodi_id,
                'status_sebelum' => $statusSebelum,
                'status_sesudah' => $statusSesudah,
                'jenis_daftar' => $data->jenis->jenisDaftar(),
                'nomor_sk' => $data->nomorSk,
                'tanggal_sk' => $data->tanggalSk?->toDateString(),
                'alasan' => $data->alasan,
                'dicatat_by_staff_id' => $staff?->id,
            ]);

            $keterangan = __('registrasi_ulang.log.'.$data->jenis->value, [
                'lama' => $lama->nim,
                'baru' => $baru?->nim ?? $lama->nim,
                'term' => $data->term->kode,
                'oleh' => $staff?->nama ?? 'sistem',
            ]);

            $lama->recordActivity('registration_'.$data->jenis->value, $keterangan);
            $baru?->recordActivity('registration_opened', $keterangan);

            return ['riwayat' => $riwayat, 'lama' => $lama, 'baru' => $baru, 'sesudah' => $statusSesudah];
        });

        $this->umumkan($hasil['lama'], $hasil['baru'], $statusSebelum, $hasil['sesudah']);

        return [
            'riwayat' => $hasil['riwayat'],
            'mahasiswa' => ($hasil['baru'] ?? $hasil['lama'])->refresh(),
            'kata_sandi' => $kataSandi,
        ];
    }

    /**
     * The new registration: the same person, a new NIM and programme, and
     * this campus as the origin PDDIKTI asks a transfer for.
     */
    private function bukaRegistrasi(Mahasiswa $lama, RegistrasiUlangData $data, string $kataSandi): Mahasiswa
    {
        $prodi = $data->prodiBaru;
        $nim = $data->nimBaru ?? $this->nim->untuk($prodi, (int) $data->term->tahun_mulai);

        if (Mahasiswa::withTrashed()->where('nim', $nim)->exists()) {
            throw new AturanAkademikException(__('registrasi_ulang.galat.nim_dipakai', ['nim' => $nim]));
        }

        $baru = Mahasiswa::create([
            ...Arr::only($lama->getAttributes(), self::KOLOM_PRIBADI),

            'nim' => $nim,
            'email' => $data->email,
            'password' => Hash::make($kataSandi),
            'prodi_id' => $prodi->id,
            'kurikulum_id' => $this->pmb->kurikulumBerlaku($prodi)?->id,
            'angkatan' => (int) $data->term->tahun_mulai,
            'term_masuk' => $data->term->kode,
            'status' => StudentStatus::Aktif,
            'is_active' => true,

            'jenis_daftar' => $data->jenis->jenisDaftar(),
            'pt_asal' => $this->branding->institutionName(),
            'prodi_asal' => $lama->prodi->namaLengkap(),
            'term_masuk_asal' => $lama->term_masuk,

            // The person PDDIKTI already holds. Without it the first sync
            // would insert a second biodata for them.
            'feeder_id' => $lama->feeder_id,
        ]);

        $baru->assignRole('mahasiswa');

        // Recognised credits are whatever the assessor approves from the old
        // transcript on the conversion screen — none yet.
        $this->konversi->segarkanSksDiakui($baru);

        return $baru;
    }

    private function pastikanBoleh(Mahasiswa $lama, RegistrasiUlangData $data): void
    {
        if (!in_array($lama->status, $data->jenis->statusAsal(), true)) {
            throw new AturanAkademikException(__('registrasi_ulang.galat.status', [
                'jenis' => $data->jenis->label(),
                'status' => $lama->status->label(),
            ]));
        }

        if (!$data->jenis->registrasiBaru()) {
            return;
        }

        if ($data->prodiBaru === null) {
            throw new AturanAkademikException(__('registrasi_ulang.galat.prodi_kosong'));
        }

        if ($data->jenis === JenisRegistrasiUlang::PindahProdi && $data->prodiBaru->id === $lama->prodi_id) {
            throw new AturanAkademikException(__('registrasi_ulang.galat.prodi_sama'));
        }

        if (blank($data->email) || Mahasiswa::withTrashed()->where('email', $data->email)->exists()) {
            throw new AturanAkademikException(__('registrasi_ulang.galat.email_dipakai', ['email' => (string) $data->email]));
        }
    }

    /** A closed registration is replaced once; a second transfer starts from the current row. */
    private function pastikanBelumDialihkan(Mahasiswa $lama, RegistrasiUlangData $data): void
    {
        if ($data->jenis->registrasiBaru() && $lama->registrasiLanjutan()->exists()) {
            throw new AturanAkademikException(__('registrasi_ulang.galat.sudah_dialihkan', ['nim' => $lama->nim]));
        }
    }

    /**
     * Campus Bridge, after the transaction.
     *
     * The new registration is a new student record and a new SSO subject,
     * so it is announced as `student.enrolled` — once, by the same publisher
     * as every other enrolment — with the earlier registration named so a
     * consumer can join the two. The old registration's status change goes
     * out as `student.status_changed`, as the yudisium's does. Reactivation
     * creates no record and announces only the status change.
     */
    private function umumkan(
        Mahasiswa $lama,
        ?Mahasiswa $baru,
        StudentStatus $sebelum,
        StudentStatus $sesudah,
    ): void {
        if ($sebelum !== $sesudah) {
            $this->bridge->publish('student.status_changed', [
                'mahasiswa_uuid' => $lama->uuid,
                'nim' => $lama->nim,
                'status_lama' => $sebelum->value,
                'status_baru' => $sesudah->value,
            ]);
        }

        if ($baru !== null) {
            $this->pendaftaran->umumkan($baru);
        }
    }
}
