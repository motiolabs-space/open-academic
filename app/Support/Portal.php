<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Akademik\TahunAkademik;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Answers "who is signed in, and into which portal" without every controller
 * and template having to guess the guard.
 *
 * Exactly one session guard can be authenticated at a time in practice; if a
 * browser somehow holds two, the first match wins deterministically.
 */
final class Portal
{
    /** Container key for the per-request memo of the active term. */
    private const TERM_KEY = 'oa.term.aktif';

    /** Container key for the per-request memo of the unread badge. */
    private const NOTIFIKASI_KEY = 'oa.notifikasi.belum';

    /**
     * The portal this request belongs to.
     *
     * The `auth:<guard>` middleware calls shouldUse() on the guard it accepted,
     * so the default driver names the portal the route actually asked for. That
     * is checked first: scanning the guards in a fixed order would hand back
     * the wrong actor whenever a browser holds sessions on two of them, and a
     * lecturer resolved on a student route is a data-leak shape, not a cosmetic
     * bug. The scan remains as a fallback for console and queue contexts.
     */
    public static function role(): ?UserRole
    {
        $diminta = UserRole::tryFrom(Auth::getDefaultDriver());

        if ($diminta !== null && Auth::guard($diminta->guard())->check()) {
            return $diminta;
        }

        foreach (UserRole::cases() as $role) {
            if (Auth::guard($role->guard())->check()) {
                return $role;
            }
        }

        return null;
    }

    public static function user(): ?Authenticatable
    {
        $role = self::role();

        return $role === null ? null : Auth::guard($role->guard())->user();
    }

    public static function nama(): string
    {
        return (string) (self::user()?->getAttribute('nama') ?? 'Tamu');
    }

    /** Two-letter monogram for the topbar avatar. */
    public static function inisial(): string
    {
        $parts = preg_split('/\s+/', self::nama()) ?: [];

        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'OA';
    }

    /** Sub-label under the avatar: "Mahasiswa · Informatika". */
    public static function konteks(): string
    {
        $user = self::user();
        $role = self::role();

        if ($user === null || $role === null) {
            return '';
        }

        $prodi = $user->getAttribute('prodi')?->nama
            ?? $user->getAttribute('unit')
            ?? $user->getAttribute('jabatan');

        return $prodi === null ? $role->label() : $role->label().' · '.$prodi;
    }

    /**
     * The active academic term, memoised for the request.
     *
     * Memoised in the container rather than a static property so the cache
     * dies with the request — a static would leak a stale term between tests
     * and between Octane requests.
     */
    public static function term(): ?TahunAkademik
    {
        // Wrapped in an array because the container resolves a bound instance
        // with isset(), and "no active term" is a legitimate null that would
        // otherwise be mistaken for an unbound key and resolved as a class.
        if (!app()->bound(self::TERM_KEY)) {
            app()->instance(self::TERM_KEY, ['term' => TahunAkademik::aktif()]);
        }

        return app(self::TERM_KEY)['term'];
    }

    /** Drops the memo — needed after a command changes which term is active. */
    public static function lupakanTerm(): void
    {
        app()->forgetInstance(self::TERM_KEY);
    }

    /** @return Collection<int, TahunAkademik> */
    public static function daftarTerm(): Collection
    {
        return TahunAkademik::terbaru()->get();
    }

    /**
     * Unread notifications for the signed-in person.
     *
     * Read on every page of every portal, which is why the notifications table
     * carries an index on (notifiable_type, notifiable_id, read_at) and why the
     * answer is memoised for the request.
     *
     * Returns zero rather than failing when the table is absent, so a partially
     * migrated installation still renders its pages instead of showing a stack
     * trace on every screen at once.
     */
    public static function notifikasiBelumDibaca(): int
    {
        if (!app()->bound(self::NOTIFIKASI_KEY)) {
            $aktor = self::user();

            $jumlah = $aktor !== null && Schema::hasTable('notifications')
                ? $aktor->unreadNotifications()->count()
                : 0;

            app()->instance(self::NOTIFIKASI_KEY, ['jumlah' => $jumlah]);
        }

        return app(self::NOTIFIKASI_KEY)['jumlah'];
    }
}
