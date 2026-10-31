<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\RecordActivityLogJob;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Whether this installation has been through the setup wizard (ADR-043).
 *
 * The wizard creates the first super-admin, so leaving it reachable on a
 * running campus would hand that account to whoever opens /setup first. The
 * rule is therefore written to fail closed: setup is open only when there is
 * no completion marker AND no account at all — staff, lecturer or student —
 * or when an operator reopened it explicitly from the command line.
 *
 * "No account at all" is what keeps an installation that predates the wizard
 * from being offered it on the day it pulls the update — it has accounts, so
 * it counts as set up even without the marker.
 */
class Instalasi
{
    public const GRUP = 'instalasi';

    public const SELESAI = 'setup_selesai_pada';

    public const DIBUKA_ULANG = 'setup_dibuka_ulang';

    /** Records that setup has run. Written by the wizard and by the demo seeder. */
    public static function tandaiSelesai(): void
    {
        Setting::put(self::GRUP, self::SELESAI, now()->toIso8601String());
        Setting::put(self::GRUP, self::DIBUKA_ULANG, '0', 'bool');
    }

    /** Reopens the wizard. Only `openacademic:setup-ulang` calls this. */
    public static function bukaUlang(): void
    {
        Setting::put(self::GRUP, self::DIBUKA_ULANG, '1', 'bool');
    }

    public static function perluSetup(): bool
    {
        if (Setting::get(self::GRUP, self::DIBUKA_ULANG) === true) {
            return true;
        }

        if (self::selesaiPada() !== null) {
            return false;
        }

        // Any account at all means somebody's data is here. Trashed rows
        // count: a campus whose only admin was soft-deleted has a recovery
        // path through artisan, not through a public web form.
        return !Staff::withTrashed()->exists()
            && !Dosen::withTrashed()->exists()
            && !Mahasiswa::withTrashed()->exists();
    }

    public static function selesaiPada(): ?string
    {
        $nilai = Setting::get(self::GRUP, self::SELESAI);

        return blank($nilai) ? null : (string) $nilai;
    }

    /**
     * An activity-log row for a change to the installation itself.
     *
     * Settings carry no trail of their own, so — like a refused IdP sign-in —
     * these are filed under a synthetic subject, "Instalasi", and still show
     * on the Log Aktivitas screen.
     *
     * @param array<string, array{old: mixed, new: mixed}> $perubahan
     */
    public static function catat(string $event, string $keterangan, array $perubahan = [], ?Staff $pelaku = null): void
    {
        $pelaku ??= Auth::guard('staff')->user();

        RecordActivityLogJob::dispatch([
            'subject_type' => 'Instalasi',
            'subject_id' => 0,
            'subject_label' => 'Instalasi',
            'event' => $event,
            'description' => Str::limit($keterangan, 255, ''),
            'changes' => $perubahan,
            'causer_type' => $pelaku?->getMorphClass(),
            'causer_id' => $pelaku?->getKey(),
            'causer_name' => $pelaku?->nama,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
