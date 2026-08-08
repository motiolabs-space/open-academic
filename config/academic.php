<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active Academic Term
    |--------------------------------------------------------------------------
    |
    | PDDIKTI term encoding: YYYYS where S = 1 (Ganjil), 2 (Genap), 3 (Antara).
    | 20261 means the odd semester of academic year 2026/2027.
    |
    | This is only a bootstrap fallback. The authoritative active term is the
    | tahun_akademik row flagged as active.
    |
    */

    'active_term' => env('ACADEMIC_ACTIVE_TERM', '20261'),

    /*
    |--------------------------------------------------------------------------
    | KRS Rules
    |--------------------------------------------------------------------------
    |
    | The credit ceiling a student may take is derived from the IPS (grade point
    | average) of the previous term. Rows are evaluated top-down; the first row
    | whose "min_ips" is satisfied wins. Students without history (new intake)
    | fall back to "default_credits".
    |
    */

    'krs' => [
        'default_credits' => 20,

        'credit_limits' => [
            ['min_ips' => 3.00, 'credits' => 24],
            ['min_ips' => 2.50, 'credits' => 22],
            ['min_ips' => 2.00, 'credits' => 20],
            ['min_ips' => 1.50, 'credits' => 18],
            ['min_ips' => 0.00, 'credits' => 15],
        ],

        // Block KRS submission until the student has paid at least
        // "min_payment_percent" of the term invoice.
        'requires_payment' => env('ACADEMIC_KRS_REQUIRES_PAYMENT', true),
        'min_payment_percent' => (int) env('ACADEMIC_KRS_MIN_PAYMENT_PERCENT', 50),

        // Enforce course prerequisites declared in the curriculum.
        'enforce_prerequisites' => true,

        // Minimum grade letter that satisfies a prerequisite.
        'prerequisite_min_grade' => 'D',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */

    'attendance' => [
        'meetings_per_term' => (int) env('ACADEMIC_MEETINGS_PER_TERM', 16),
        'min_percent_for_final_exam' => (int) env('ACADEMIC_MIN_ATTENDANCE_PERCENT', 75),

        // Lifetime of a QR attendance session, in seconds.
        'qr_session_ttl' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Grading Scale
    |--------------------------------------------------------------------------
    |
    | Institution-configurable letter scale. "min_score" is inclusive and rows
    | must be ordered from highest to lowest.
    |
    */

    'grading' => [
        'scale' => [
            ['letter' => 'A', 'min_score' => 80.0, 'weight' => 4.00],
            ['letter' => 'AB', 'min_score' => 75.0, 'weight' => 3.50],
            ['letter' => 'B', 'min_score' => 70.0, 'weight' => 3.00],
            ['letter' => 'BC', 'min_score' => 65.0, 'weight' => 2.50],
            ['letter' => 'C', 'min_score' => 55.0, 'weight' => 2.00],
            ['letter' => 'D', 'min_score' => 45.0, 'weight' => 1.00],
            ['letter' => 'E', 'min_score' => 0.0, 'weight' => 0.00],
        ],

        // Default weighted components applied to a new kelas kuliah.
        'default_components' => [
            ['name' => 'Tugas', 'weight' => 30],
            ['name' => 'UTS', 'weight' => 30],
            ['name' => 'UAS', 'weight' => 40],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Final Project (Tugas Akhir / Skripsi / Tesis / Disertasi)
    |--------------------------------------------------------------------------
    |
    | Whether a programme requires one at all is a per-programme decision, on
    | prodi.wajib_tugas_akhir. These are the numbers that apply once it does.
    |
    */

    'tugas_akhir' => [
        // Credits a student must already have passed before proposing. The
        // rule exists because supervision is the scarcest resource on campus:
        // a first-year proposing occupies a slot for years.
        'min_sks_pengajuan' => (int) env('ACADEMIC_TA_MIN_SKS', 110),

        // Live projects one lecturer may supervise. Set to 0 to disable the
        // cap — which is what happens in practice when nobody counts: a
        // popular supervisor ends up with forty students and none of them are
        // actually supervised.
        'kuota_pembimbing' => (int) env('ACADEMIC_TA_KUOTA_PEMBIMBING', 10),

        // Signed-off consultations required before a defence may be scheduled.
        // Counts approved rows only; an unapproved log is a claim, not a record.
        'min_bimbingan_sebelum_sidang' => (int) env('ACADEMIC_TA_MIN_BIMBINGAN', 8),

        // How long a project may run before it is flagged overdue, in months.
        // Nothing is cancelled automatically — expiry is a conversation between
        // a student and a department, and software that ends it unilaterally
        // just gets a new project row created to work around it.
        'batas_bulan' => (int) env('ACADEMIC_TA_BATAS_BULAN', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graduation
    |--------------------------------------------------------------------------
    */

    'graduation' => [
        'min_credits' => 144,
        'min_gpa' => 2.00,

        // Predikat kelulusan thresholds, evaluated top-down against the IPK.
        'honours' => [
            ['min_gpa' => 3.51, 'label' => 'Dengan Pujian'],
            ['min_gpa' => 3.01, 'label' => 'Sangat Memuaskan'],
            ['min_gpa' => 2.76, 'label' => 'Memuaskan'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Student Number (NIM) Generator
    |--------------------------------------------------------------------------
    |
    | Placeholders: {year} intake year (4 digits), {yy} intake year (2 digits),
    | {prodi} program study code, {seq} zero-padded sequence.
    |
    */

    'nim' => [
        'pattern' => '{yy}{prodi}{seq}',
        'sequence_length' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Diploma Number
    |--------------------------------------------------------------------------
    |
    | Placeholders: {tahun} ceremony year, {prodi} programme code, {urut} the
    | participant's zero-padded queue number.
    |
    | Every campus has its own convention, and this number is printed on a
    | document somebody keeps for life and quotes on every job application — so
    | settle it before the first ceremony runs, not after.
    |
    */

    'ijazah' => [
        'pattern' => env('ACADEMIC_IJAZAH_PATTERN', '{tahun}/{prodi}/{urut}'),
    ],

];
