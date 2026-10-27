<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| KKN / PKL as managed field programmes (#37)
|--------------------------------------------------------------------------
|
| Defaults copied onto a new programme run. Each run keeps its own copy, so
| changing these later never reshapes groups already formed.
|
*/

return [
    // Minimum cumulative credits to join. 0 = no floor.
    'min_sks' => (int) env('KEGIATAN_LAPANGAN_MIN_SKS', 0),

    'kelompok' => [
        // Target members per group. Groups are evened out, so with 23
        // participants and a target of 10 the result is 8 + 8 + 7, not
        // 10 + 10 + 3.
        'ukuran' => (int) env('KEGIATAN_LAPANGAN_UKURAN_KELOMPOK', 10),

        // Spread programmes and sexes across groups rather than keeping them
        // together. Turning one off forms groups within that attribute instead.
        'campur_prodi' => (bool) env('KEGIATAN_LAPANGAN_CAMPUR_PRODI', true),
        'campur_jenis_kelamin' => (bool) env('KEGIATAN_LAPANGAN_CAMPUR_JENIS_KELAMIN', true),
    ],

    // Share of the final mark given by the partner supervisor, in percent; the
    // field supervisor gives the rest.
    'bobot_mitra' => (int) env('KEGIATAN_LAPANGAN_BOBOT_MITRA', 40),
];
