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

];
