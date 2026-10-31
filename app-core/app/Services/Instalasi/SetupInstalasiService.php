<?php

declare(strict_types=1);

namespace App\Services\Instalasi;

use App\DTOs\Instalasi\SetupInstalasiData;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Prodi;
use App\Models\Sdm\Staff;
use App\Models\System\Setting;
use App\Policies\Sdm\StaffPolicy;
use App\Services\Akademik\TahunAkademikService;
use App\Services\Dokumen\PengaturanDokumen;
use App\Support\Instalasi;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The first-run wizard: institution identity, the first super-admin, and
 * optionally a faculty, a programme and the running term (ADR-043).
 *
 * Runs once. The closing step writes the marker that makes /setup answer 404
 * from then on, inside the same lock and transaction as the account it
 * creates — two browsers submitting at the same moment get one super-admin
 * and one 404, not two super-admins.
 */
class SetupInstalasiService
{
    public function __construct(
        private readonly TahunAkademikService $tahunAkademik,
        private readonly TelemetriService $telemetri,
    ) {}

    public function terbuka(): bool
    {
        return Instalasi::perluSetup();
    }

    /** @throws NotFoundHttpException when setup already ran */
    public function jalankan(SetupInstalasiData $data): Staff
    {
        $admin = Cache::lock('instalasi.setup', 30)->block(10, function () use ($data): Staff {
            if (!$this->terbuka()) {
                throw new NotFoundHttpException;
            }

            $logo = $data->logo?->store('branding', 'public');

            return DB::transaction(function () use ($data, $logo): Staff {
                $this->simpanIdentitas($data, $logo);

                $admin = $this->buatSuperAdmin($data);

                $this->buatStrukturAwal($data);

                $this->telemetri->idInstalasi();
                $this->telemetri->atur($data->telemetri, kirim: false);

                Instalasi::tandaiSelesai();

                Instalasi::catat('installation_setup', __('instalasi.log.setup_selesai', [
                    'pt' => $data->namaPt,
                    'admin' => $data->adminEmail,
                ]), pelaku: $admin);

                return $admin;
            });
        });

        // After commit, outside the lock: a slow endpoint on a sync queue must
        // not hold the setup lock, and a failed ping must not roll setup back.
        // atur() above stored the consent; this sends.
        $this->telemetri->laporkan();

        return $admin;
    }

    private function simpanIdentitas(SetupInstalasiData $data, ?string $logo): void
    {
        $branding = [
            'institution_name' => $data->namaPt,
            'institution_short' => $data->singkatan,
            'institution_code' => $data->kodePt,
            'institution_address' => $data->alamat,
            'institution_city' => $data->kota,
            'institution_province' => $data->provinsi,
            'institution_email' => $data->surel,
            'institution_phone' => (string) $data->telepon,
            'institution_website' => (string) $data->situs,
            'primary_color' => $data->warnaUtama,
            'accent_color' => $data->warnaAksen,
        ];

        if ($logo !== null) {
            $branding['logo_path'] = $logo;
        }

        foreach ($branding as $kunci => $nilai) {
            Setting::put('branding', $kunci, $nilai);
        }

        // The letterhead was going to ask for the same address and contacts;
        // prefilled once here, editable under Pengaturan > Dokumen Cetak.
        $kop = [
            'kop_alamat' => implode(', ', array_filter([$data->alamat, $data->kota, $data->provinsi])),
            'kop_kontak' => implode(' · ', array_filter([$data->telepon, $data->surel, $data->situs])),
        ];

        foreach ($kop as $kunci => $nilai) {
            if (blank(Setting::get(PengaturanDokumen::GRUP, $kunci))) {
                Setting::put(PengaturanDokumen::GRUP, $kunci, $nilai);
            }
        }
    }

    private function buatSuperAdmin(SetupInstalasiData $data): Staff
    {
        // `migrate` alone leaves no roles behind; the wizard must not depend
        // on the operator having remembered `db:seed` as well.
        if (!Role::query()->where('name', StaffPolicy::PERAN_TERTINGGI)->where('guard_name', 'staff')->exists()) {
            app(RolePermissionSeeder::class)->run();
        }

        $admin = Staff::create([
            'nama' => $data->adminNama,
            'email' => $data->adminEmail,
            'nip' => $data->adminNip,
            'password' => $data->adminPassword,
            'jabatan' => __('instalasi.admin_jabatan'),
            'is_active' => true,
        ]);

        $admin->assignRole(StaffPolicy::PERAN_TERTINGGI);

        return $admin;
    }

    private function buatStrukturAwal(SetupInstalasiData $data): void
    {
        if ($data->adaFakultas()) {
            $fakultas = Fakultas::create([
                'kode' => $data->fakultasKode,
                'nama' => $data->fakultasNama,
            ]);

            if ($data->adaProdi()) {
                Prodi::create([
                    'fakultas_id' => $fakultas->id,
                    'kode' => $data->prodiKode,
                    'nama' => $data->prodiNama,
                    'jenjang' => $data->prodiJenjang,
                    'is_active' => true,
                ]);
            }
        }

        if ($data->adaSemester()) {
            $term = $this->tahunAkademik->buat([...$data->kalender, 'semester' => $data->semester]);

            $this->tahunAkademik->aktifkan($term);
        }
    }
}
