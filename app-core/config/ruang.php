<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Room utilisation (#42)
|--------------------------------------------------------------------------
|
| A report, not a booking system: utilisation is read from the class
| timetable and the published exam timetable of a term. What the report
| cannot know on its own is when a room *could* be used — that is a campus
| decision, and it is the denominator of every percentage on the screen.
|
*/

return [
    'operasional' => [
        // ISO weekdays the rooms are open: 1 = Senin ... 7 = Minggu.
        'hari' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('RUANG_HARI_OPERASIONAL', '1,2,3,4,5')),
        ), fn (int $hari): bool => $hari >= 1 && $hari <= 7)),

        // Opening window on every operating day, "HH:MM". Use outside it is
        // reported separately rather than inflating the percentage past 100.
        'jam_mulai' => (string) env('RUANG_JAM_MULAI', '07:00'),
        'jam_selesai' => (string) env('RUANG_JAM_SELESAI', '17:00'),
    ],

    // A room used for less than this share of its available hours is listed as
    // rarely used, in percent.
    'ambang_jarang_persen' => (int) env('RUANG_AMBANG_JARANG', 25),

    // Width of one heat-map column, in minutes.
    'langkah_peta_menit' => (int) env('RUANG_LANGKAH_PETA', 60),

    'ekspor' => [
        // Excel on an Indonesian Windows install reads a BOM-less UTF-8 CSV as
        // mojibake.
        'bom_utf8' => (bool) env('RUANG_CSV_BOM', true),
    ],
];
