<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Campus Bridge
    |--------------------------------------------------------------------------
    |
    | Campus Bridge is the only sanctioned way for external systems (notably
    | Open Campus) to reach Open Academic data. The database is never shared:
    | consumers authenticate with a scoped Sanctum token over HTTPS, and are
    | notified of changes through signed webhooks.
    |
    */

    'enabled' => env('BRIDGE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Token Scopes
    |--------------------------------------------------------------------------
    |
    | A consumer application may only read the resources covered by the scopes
    | granted to its token.
    |
    */

    'scopes' => [
        'students.read' => 'Baca data mahasiswa & status per semester',
        'lecturers.read' => 'Baca data dosen & penugasan',
        'classes.read' => 'Baca kelas kuliah & metode pembelajaran',
        'activities.read' => 'Baca aktivitas mahasiswa (MBKM)',
        'graduates.read' => 'Baca data lulusan & yudisium',
        'terms.read' => 'Baca kalender & tahun akademik',

        // Aggregates only. There is no scope that returns an individual answer
        // or a free-text comment, because no endpoint exists that could serve
        // one — see TeachingEvaluationController.
        'evaluations.read' => 'Baca rekap agregat evaluasi dosen (EDOM)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Events
    |--------------------------------------------------------------------------
    */

    'events' => [
        'student.enrolled',
        'student.status_changed',
        'krs.approved',
        'grade.finalized',
        'student.graduated',
        'activity.recorded',
        'lecturer.assignment_recorded',
    ],

    'webhooks' => [
        // Fallback signing secret; each subscription stores its own.
        'secret' => env('BRIDGE_WEBHOOK_SECRET'),

        'signature_header' => 'X-OpenAcademic-Signature',
        'timestamp_header' => 'X-OpenAcademic-Timestamp',
        'algorithm' => 'sha256',

        'timeout' => (int) env('BRIDGE_WEBHOOK_TIMEOUT', 15),
        'max_attempts' => (int) env('BRIDGE_WEBHOOK_MAX_ATTEMPTS', 5),

        // Backoff in seconds per attempt.
        'backoff' => [60, 300, 900, 3600, 21600],
    ],

    /*
    |--------------------------------------------------------------------------
    | Read API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'prefix' => 'api/bridge/v1',
        'per_page' => 50,
        'max_per_page' => 200,
        'rate_limit' => '120,1', // requests, minutes
    ],

];
