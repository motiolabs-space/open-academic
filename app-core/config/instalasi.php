<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | Bumped together with CHANGELOG.md on every release. It is in version
    | control rather than .env on purpose: it describes the code that is
    | deployed, not the campus that deployed it. The installation ping is the
    | only thing that reads it (see docs/TELEMETRI.md).
    |
    */

    'versi' => '1.1.0-dev',

    /*
    |--------------------------------------------------------------------------
    | Installation Ping to Humanix (ADR-043)
    |--------------------------------------------------------------------------
    |
    | Off unless the campus ticks the consent box on the last step of the
    | setup wizard, or later under Pengaturan. `aktif` can only take that
    | consent away: HUMANIX_TELEMETRI=false silences the ping whatever the
    | screen says, and HUMANIX_TELEMETRI=true does NOT switch it on without
    | consent. The full list of what is sent is in docs/TELEMETRI.md.
    |
    */

    'telemetri' => [
        'aktif' => env('HUMANIX_TELEMETRI', true),

        // "http" posts to the endpoint below; "palsu" records the payload in
        // memory and never touches the network (tests, air-gapped campuses).
        'driver' => env('HUMANIX_TELEMETRI_DRIVER', 'http'),

        /*
         * PLACEHOLDER. The receiving endpoint on humanix.id is not part of
         * this repository and has to be built separately; until it exists
         * every ping is recorded locally as failed, which is harmless.
         */
        'endpoint' => env('HUMANIX_TELEMETRI_URL', 'https://humanix.id/api/open-academic/v1/installations'),

        // Seconds. Short, because nothing waits on the answer.
        'timeout' => 5,
        'connect_timeout' => 3,

        // Attempts per ping, and the pause before each retry (seconds).
        'percobaan' => 3,
        'jeda' => [60, 600],
    ],

];
