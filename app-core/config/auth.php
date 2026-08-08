<?php

declare(strict_types=1);

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Open Academic has no generic "user". Staff, lecturers and students are
    | distinct domain entities on distinct guards, so a lecturer session can
    | never be mistaken for a student session and an authorisation bug in one
    | portal cannot leak into another.
    |
    | "staff" is the default only because the admin portal is the widest
    | surface; every route still declares its guard explicitly.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'staff'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'staff'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'staff' => [
            'driver' => 'session',
            'provider' => 'staff',
        ],

        'dosen' => [
            'driver' => 'session',
            'provider' => 'dosen',
        ],

        'mahasiswa' => [
            'driver' => 'session',
            'provider' => 'mahasiswa',
        ],

        /*
        | The guard Passport uses to decide who is authorising an OAuth client.
        | It owns no session of its own — it reads whichever of the three above
        | is signed in, so nobody is asked to log in twice.
        */
        'sso' => [
            'driver' => 'sso-session',
            'provider' => 'akademik',
        ],

        /*
        | Bearer-token access for consumers acting on behalf of a person. The
        | Campus Bridge read API is separate and uses Sanctum tokens issued to
        | the consumer application itself, not to a human.
        */
        'api' => [
            'driver' => 'passport',
            'provider' => 'akademik',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'staff' => [
            'driver' => 'eloquent',
            'model' => Staff::class,
        ],

        'dosen' => [
            'driver' => 'eloquent',
            'model' => Dosen::class,
        ],

        'mahasiswa' => [
            'driver' => 'eloquent',
            'model' => Mahasiswa::class,
        ],

        /*
        | Resolves an OAuth subject (a UUID) across all three identity tables.
        | Deliberately has no single `model`: the whole point is that a subject
        | may be any of the three.
        */
        'akademik' => [
            'driver' => 'akademik',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | All three brokers share the password_reset_tokens table, which is keyed
    | by email — unique across the three tables by institutional policy.
    |
    */

    'passwords' => [
        'staff' => [
            'provider' => 'staff',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'dosen' => [
            'provider' => 'dosen',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'mahasiswa' => [
            'provider' => 'mahasiswa',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => (int) env('AUTH_PASSWORD_TIMEOUT', 10800),

];
