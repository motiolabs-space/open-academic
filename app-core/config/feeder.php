<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Neo Feeder (PDDIKTI) Connection
    |--------------------------------------------------------------------------
    |
    | Neo Feeder is installed on-premise by the institution and exposes a JSON
    | web service, by default at http://<host>:3003/ws/live2.php. Every request
    | carries an act name plus a token obtained through the GetToken action.
    |
    | Driver "fake" wires FakeFeederClient, which serves fixtures instead of
    | hitting the network — used in tests and in the demo installation.
    |
    */

    'enabled' => env('FEEDER_ENABLED', false),

    'driver' => env('FEEDER_DRIVER', 'fake'), // fake | live

    'base_url' => env('FEEDER_BASE_URL', 'http://localhost:3003/ws/live2.php'),

    'credentials' => [
        'username' => env('FEEDER_USERNAME'),
        'password' => env('FEEDER_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Behaviour
    |--------------------------------------------------------------------------
    */

    'token_ttl' => (int) env('FEEDER_TOKEN_TTL', 3600),
    'timeout' => (int) env('FEEDER_TIMEOUT', 60),
    'retry_times' => 3,
    'retry_sleep_ms' => 2000,

    // Rows pushed per job when syncing an entity for a term.
    'batch_size' => 100,

    /*
    |--------------------------------------------------------------------------
    | Reference Tables
    |--------------------------------------------------------------------------
    |
    | Feeder reference data is pulled first and cached locally in feeder_ref_*
    | tables; local enums map onto Feeder codes through the FeederMapping model.
    |
    */

    'references' => [
        'agama' => 'GetAgama',
        'wilayah' => 'GetWilayah',
        'jenjang_pendidikan' => 'GetJenjangPendidikan',
        'status_mahasiswa' => 'GetStatusMahasiswa',
        'jenis_keluar' => 'GetJenisKeluar',
        'ikatan_kerja' => 'GetIkatanKerjaDosen',
        'jenis_evaluasi' => 'GetJenisEvaluasi',
        'substansi_kuliah' => 'GetSubstansiKuliah',
    ],

    /*
    |--------------------------------------------------------------------------
    | Syncable Entities
    |--------------------------------------------------------------------------
    |
    | Order matters: an entity may only be pushed after its dependencies are
    | already known to Feeder.
    |
    */

    'entities' => [
        'mahasiswa' => [
            'label' => 'Biodata Mahasiswa',
            'action' => 'InsertBiodataMahasiswa',
            'depends_on' => [],
        ],
        'riwayat_pendidikan' => [
            'label' => 'Riwayat Pendidikan Mahasiswa',
            'action' => 'InsertRiwayatPendidikanMahasiswa',
            'depends_on' => ['mahasiswa'],
        ],
        'aktivitas_kuliah' => [
            'label' => 'Aktivitas Kuliah Mahasiswa',
            'action' => 'InsertAktivitasKuliahMahasiswa',
            'depends_on' => ['riwayat_pendidikan'],
        ],
        'kelas_kuliah' => [
            'label' => 'Kelas Kuliah',
            'action' => 'InsertKelasKuliah',
            'depends_on' => [],
        ],
        'krs' => [
            'label' => 'KRS Mahasiswa',
            'action' => 'InsertKRSMahasiswa',
            'depends_on' => ['aktivitas_kuliah', 'kelas_kuliah'],
        ],
        'nilai' => [
            'label' => 'Nilai Perkuliahan Kelas',
            'action' => 'InsertNilaiPerkuliahanKelas',
            'depends_on' => ['krs'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    |
    | The sync ledger records what left this building. It cannot record what
    | PDDIKTI actually holds: a row edited by an operator inside Feeder, or one
    | entered there directly, is invisible to a push-only sync no matter how
    | carefully the ledger is kept. Reconciliation reads the other end back and
    | reports where the two disagree.
    |
    | An entity absent from this list is reported as "belum dapat dibandingkan"
    | — never as "cocok". A comparison that cannot run has not found agreement.
    |
    | Per entity:
    |
    |   get_action  the Feeder act that lists the entity. Act names differ
    |               between Feeder builds; a wrong one produces a Feeder error,
    |               which is surfaced rather than swallowed, so it corrects
    |               itself on first run instead of quietly reporting zero.
    |
    |   filter      sent with the request. ":term" is replaced by the academic
    |               term code, which is what mappers already write into
    |               id_semester.
    |
    |   key         fields that identify one row. Taken from the payload we
    |               send AND from the row Feeder returns — the same field names
    |               on both sides, because they are Feeder's names to begin
    |               with. Composite where no single field is unique.
    |
    | Biodata Mahasiswa is deliberately absent. Its only unique field is the
    | NIK, and matching on it would copy every student's NIK into the diff
    | table and onto a screen that lists them. The registration record carries
    | the NIM and covers the same students without that.
    |
    */

    'reconcile' => [

        'kelas_kuliah' => [
            'get_action' => 'GetListKelasKuliah',
            'filter' => ['id_semester' => ':term'],
            'key' => ['id_semester', 'id_matkul', 'nama_kelas_kuliah'],
        ],

        'aktivitas_kuliah' => [
            'get_action' => 'GetListAktivitasKuliahMahasiswa',
            'filter' => ['id_semester' => ':term'],
            'key' => ['id_registrasi_mahasiswa', 'id_semester'],
        ],

        'krs' => [
            'get_action' => 'GetListKRSMahasiswa',
            'filter' => ['id_semester' => ':term'],
            'key' => ['id_registrasi_mahasiswa', 'id_kelas_kuliah'],
        ],

    ],

    // Rows pulled per page when reading Feeder back. Higher is fewer requests
    // and more memory; a term of KRS at a mid-sized campus is tens of
    // thousands of rows, so it is paged rather than fetched whole.
    'reconcile_page_size' => 500,

];
