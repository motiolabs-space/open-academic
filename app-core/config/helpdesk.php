<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pola Nomor Tiket
    |--------------------------------------------------------------------------
    |
    | Placeholder: {tahun} tahun empat digit, {urut} nomor urut ber-padding.
    | Nomor tiket disebut orang lewat telepon dan ditulis di surel balasan,
    | jadi pendek dan mudah dieja lebih penting daripada memuat banyak kode.
    |
    */

    'pola_nomor' => env('HELPDESK_POLA_NOMOR', 'HD-{tahun}-{urut}'),

    'panjang_urut' => 5,

    /*
    |--------------------------------------------------------------------------
    | SLA Bawaan (jam)
    |--------------------------------------------------------------------------
    |
    | Dipakai bila kategori tiket tidak menetapkan SLA-nya sendiri. Null berarti
    | tiket kategori itu tidak pernah ditandai terlambat — bukan "tidak penting",
    | melainkan "tidak dijanjikan".
    |
    */

    'sla_jam_bawaan' => env('HELPDESK_SLA_JAM', 72),

    /*
    |--------------------------------------------------------------------------
    | Lampiran
    |--------------------------------------------------------------------------
    |
    | Folder di disk berkas privat (lihat config/berkas.php). Lampiran tiket
    | sering berupa tangkapan layar dan bukti transfer; keduanya pribadi.
    |
    */

    'folder_lampiran' => 'helpdesk',

];
