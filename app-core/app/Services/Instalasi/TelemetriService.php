<?php

declare(strict_types=1);

namespace App\Services\Instalasi;

use App\DTOs\Instalasi\PersetujuanTelemetriData;
use App\Enums\PeristiwaInstalasi;
use App\Enums\StatusPingInstalasi;
use App\Jobs\KirimPingInstalasiJob;
use App\Models\System\InstallationPing;
use App\Models\System\Setting;
use App\Services\Branding\BrandingService;
use App\Support\Instalasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The installation ping to Humanix: consent, payload, and when to send (ADR-043).
 *
 * Three rules shape everything here:
 *
 *  1. Nothing is sent without consent given on screen, and HUMANIX_TELEMETRI=false
 *     overrides that consent. The environment can only take consent away.
 *  2. The payload is a fixed list of fields about the institution and the
 *     software — never about a student, a lecturer, a credential or the key.
 *  3. One ping per application version, never periodic. Running `migrate`
 *     after an upgrade is what notices a new version.
 */
class TelemetriService
{
    private const SETUJU = 'telemetri_setuju';

    private const SERTAKAN_URL = 'telemetri_sertakan_url';

    private const DIUBAH_PADA = 'telemetri_diubah_pada';

    private const ID = 'id_instalasi';

    public function __construct(private readonly BrandingService $brand) {}

    /** False only when the operator set HUMANIX_TELEMETRI=false. */
    public function diizinkanEnv(): bool
    {
        $nilai = config('instalasi.telemetri.aktif');

        // Unset or empty leaves the decision to the screen; filter_var alone
        // would read both as false.
        if ($nilai === null || $nilai === '') {
            return true;
        }

        return filter_var($nilai, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function disetujui(): bool
    {
        return Setting::get(Instalasi::GRUP, self::SETUJU) === true;
    }

    public function sertakanUrl(): bool
    {
        return Setting::get(Instalasi::GRUP, self::SERTAKAN_URL) === true;
    }

    public function diubahPada(): ?string
    {
        $nilai = Setting::get(Instalasi::GRUP, self::DIUBAH_PADA);

        return blank($nilai) ? null : (string) $nilai;
    }

    /** Whether a ping would actually be sent right now. */
    public function aktif(): bool
    {
        return $this->diizinkanEnv() && $this->disetujui();
    }

    /**
     * Records the campus's answer, and sends the first ping if it is a yes.
     *
     * Audited: switching on a flow of data to a third party is exactly the kind
     * of decision someone asks about later. `$kirim` is false only from the
     * setup wizard, which sends after its own transaction has committed.
     */
    public function atur(PersetujuanTelemetriData $data, bool $kirim = true): ?InstallationPing
    {
        $sebelum = ['setuju' => $this->disetujui(), 'sertakan_url' => $this->sertakanUrl()];
        $sesudah = ['setuju' => $data->setuju, 'sertakan_url' => $data->sertakanUrl];

        Setting::put(Instalasi::GRUP, self::SETUJU, $data->setuju ? '1' : '0', 'bool');
        Setting::put(Instalasi::GRUP, self::SERTAKAN_URL, $data->sertakanUrl ? '1' : '0', 'bool');

        if ($sebelum !== $sesudah) {
            Setting::put(Instalasi::GRUP, self::DIUBAH_PADA, now()->toIso8601String());

            Instalasi::catat(
                'telemetry_consent_changed',
                __($data->setuju ? 'instalasi.log.telemetri_aktif' : 'instalasi.log.telemetri_nonaktif'),
                collect($sesudah)->map(fn (bool $baru, string $kunci): array => [
                    'old' => $sebelum[$kunci],
                    'new' => $baru,
                ])->all(),
            );
        }

        return $kirim ? $this->laporkan() : null;
    }

    /**
     * Random, and generated once. Deliberately not derived from anything about
     * the campus: a hash of the institution code would be a pseudonym anyone
     * could reverse by hashing the public list of codes.
     */
    public function idInstalasi(): string
    {
        $id = Setting::get(Instalasi::GRUP, self::ID);

        if (is_string($id) && Str::isUuid($id)) {
            return $id;
        }

        $id = (string) Str::uuid();
        Setting::put(Instalasi::GRUP, self::ID, $id);

        return $id;
    }

    /**
     * The exact body of a ping. docs/TELEMETRI.md lists these fields one by
     * one; adding a field here without updating it (and asking again) breaks
     * the promise made on the consent screen.
     *
     * @return array<string, string>
     */
    public function muatan(PeristiwaInstalasi $peristiwa): array
    {
        return $this->susunMuatan($peristiwa, $this->idInstalasi());
    }

    /**
     * What the next ping would contain, for Pengaturan and `openacademic:telemetri`.
     * Read-only: it does not mint the installation id just because someone looked.
     *
     * @return array<string, string>
     */
    public function pratinjau(): array
    {
        $id = Setting::get(Instalasi::GRUP, self::ID);

        return $this->susunMuatan(
            InstallationPing::query()->where('status', StatusPingInstalasi::Sent)->exists()
                ? PeristiwaInstalasi::Diperbarui
                : PeristiwaInstalasi::Dipasang,
            is_string($id) && Str::isUuid($id) ? $id : __('instalasi.telemetri.id_belum_ada'),
        );
    }

    /** @return array<string, string> */
    private function susunMuatan(PeristiwaInstalasi $peristiwa, string $id): array
    {
        $muatan = [
            'installation_id' => $id,
            'event' => $peristiwa->value,
            'institution_name' => $this->brand->institutionName(),
            'institution_code' => $this->brand->institutionCode(),
            'city' => $this->brand->institutionCity(),
            'province' => $this->brand->institutionProvince(),
            'app_version' => (string) config('instalasi.versi'),
            // Major.minor only: a patch level says which fixes are missing.
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'db_engine' => $this->mesinBasisData(),
            'date' => now()->toDateString(),
        ];

        if ($this->sertakanUrl()) {
            $muatan['app_url'] = (string) config('app.url');
        }

        return $muatan;
    }

    /**
     * Queues the ping for the running version, unless it was already sent.
     *
     * Idempotent: a version has one row, reused on every call, so running this
     * after every `migrate` is safe. Never throws — a broken queue must not
     * turn into a failed setup or a failed deploy.
     */
    public function laporkan(): ?InstallationPing
    {
        if (!$this->aktif()) {
            return null;
        }

        try {
            $ping = DB::transaction(function (): ?InstallationPing {
                $versi = (string) config('instalasi.versi');

                $ping = InstallationPing::query()->where('app_version', $versi)->lockForUpdate()->first();

                if ($ping?->status === StatusPingInstalasi::Sent) {
                    return null;
                }

                $pernahTerkirim = InstallationPing::query()->where('status', StatusPingInstalasi::Sent)->exists();

                $ping ??= new InstallationPing(['app_version' => $versi]);
                $ping->fill([
                    'event' => $pernahTerkirim ? PeristiwaInstalasi::Diperbarui : PeristiwaInstalasi::Dipasang,
                    'status' => StatusPingInstalasi::Queued,
                    'attempts' => 0,
                ])->save();

                return $ping;
            });

            if ($ping === null) {
                return null;
            }

            KirimPingInstalasiJob::dispatch($ping->id);

            return $ping->refresh();
        } catch (Throwable $e) {
            Log::warning('Ping instalasi tidak dapat diantrekan.', ['galat' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * "mysql", "mariadb", "sqlite" or "pgsql" — the engine, never its host,
     * database name or exact version.
     */
    private function mesinBasisData(): string
    {
        $koneksi = DB::connection();
        $driver = $koneksi->getDriverName();

        if ($driver !== 'mysql') {
            return $driver;
        }

        try {
            // A MariaDB server is often reached through the "mysql" driver.
            return str_contains(strtolower($koneksi->getServerVersion()), 'mariadb') ? 'mariadb' : 'mysql';
        } catch (Throwable) {
            return 'mysql';
        }
    }
}
