<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ambang Responden Kuesioner Anonim
    |--------------------------------------------------------------------------
    |
    | Hasil kuesioner anonim tertahan sampai sekurang-kurangnya sekian orang
    | mengisi. Rerata dari dua orang menunjuk keduanya; komentar dari tiga orang
    | dikenali dari gayanya. Sama dengan ambang EDOM, dan sama alasannya.
    |
    | Nilai ini hanya bawaan formulir: setiap kuesioner menyimpan ambangnya
    | sendiri saat dibuat, jadi mengubahnya tidak membuka hasil yang sudah
    | tertahan. Kuesioner yang tidak anonim tidak memakai ambang.
    |
    */

    'min_responden' => (int) env('KUESIONER_MIN_RESPONDEN', 5),

    // Batas jawaban isian bebas yang ditampilkan per pertanyaan di layar
    // hasil. Selebihnya ada di berkas ekspor.
    'tampil_teks' => 50,

];
