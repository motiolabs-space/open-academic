<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Top-level actor type. Each role maps onto its own auth guard so a lecturer
 * session can never be mistaken for a student session, and onto a Spatie
 * Permission guard name of the same string.
 */
enum UserRole: string
{
    case Staff = 'staff';
    case Dosen = 'dosen';
    case Mahasiswa = 'mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staf / BAAK',
            self::Dosen => 'Dosen',
            self::Mahasiswa => 'Mahasiswa',
        };
    }

    /** Laravel auth guard name. */
    public function guard(): string
    {
        return $this->value;
    }

    /** Landing route after login. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Staff => 'admin.dashboard',
            self::Dosen => 'dosen.dashboard',
            self::Mahasiswa => 'mahasiswa.dashboard',
        };
    }

    /** Route name of the login form for this role. */
    public function loginRoute(): string
    {
        return 'login';
    }
}
