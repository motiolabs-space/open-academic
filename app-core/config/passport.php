<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSsoRoleAllowed;

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    | Open Academic has no "web" guard. Authorisation runs on "sso", which owns
    | no session of its own and instead reports whichever of the three portal
    | guards (mahasiswa / dosen / staff) is currently signed in — so nobody is
    | asked to log in a second time to authorise an app. See App\Auth\SsoGuard.
    |
    */

    'guard' => 'sso',

    /*
    | Applied to every /oauth route. EnsureSsoRoleAllowed is a no-op on the
    | token and device endpoints, which carry no session user.
    */
    'middleware' => [
        EnsureSsoRoleAllowed::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
