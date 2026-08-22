<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Verifikasi Dua Langkah untuk Staf
    |--------------------------------------------------------------------------
    |
    | Akun staf memegang nilai.manage, keuangan.manage, dan wisuda.manage. Satu
    | kata sandi bocor di sana tidak membocorkan catatan — ia MENGUBAHNYA, dan
    | kelulusan yang terlanjur terbit tidak dapat ditarik pulang dengan mengganti
    | kata sandi.
    |
    | Staf saja, dan itu disengaja. Populasinya puluhan alih-alih ribuan,
    | sehingga gesekannya jatuh pada segelintir orang yang memang memegang
    | kewenangan berbahaya — bukan pada setiap mahasiswa di pekan KRS.
    |
    */

    /*
    | Mewajibkan seluruh staf memasangnya.
    |
    | Bawaannya FALSE, dan itu bukan penilaian bahwa fitur ini opsional.
    | Memasang true sebagai bawaan akan mengunci setiap pemasangan yang sudah
    | berjalan pada hari mereka menarik pembaruan — seluruh stafnya tiba di
    | layar pemasangan sekaligus, termasuk yang sedang mengejar tenggat.
    |
    | Kampus menyalakannya lewat .env sesudah stafnya didampingi memasang:
    |
    |     DUA_FAKTOR_WAJIB=true
    |
    | Ketika true, staf tanpa faktor kedua diarahkan ke layar pemasangan dan
    | tidak dapat membuka layar lain sampai selesai — dan tidak dapat
    | mematikannya sendiri.
    |
    | Tercantum juga di SECURITY.md sebagai langkah sebelum produksi.
    */
    'wajib' => (bool) env('DUA_FAKTOR_WAJIB', false),

];
