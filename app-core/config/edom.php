<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Gerbang Pengisian
    |--------------------------------------------------------------------------
    |
    | EDOM yang tidak digerbangi diisi belasan persen mahasiswa, dan hasilnya
    | condong ke yang paling puas dan yang paling marah. Gerbang adalah satu-
    | satunya cara memperoleh tingkat pengisian yang berarti.
    |
    | Tiga pilihan, dan pilihannya bukan sekadar teknis:
    |
    |   "nonaktif" — tanpa gerbang.
    |
    |   "krs"      — mahasiswa tidak dapat mengajukan KRS semester berikutnya
    |                sebelum EDOM semester berjalan lengkap. Bawaan.
    |
    |   "khs"      — mahasiswa tidak dapat melihat KHS sebelum EDOM lengkap.
    |
    | Bawaannya "krs", dan itu posisi. Keduanya sama-sama memaksa, tetapi menahan
    | KHS berarti menahan nilai yang sudah diperoleh mahasiswa sebagai alat tukar
    | untuk sebuah survei — memakai catatan akademik seseorang sebagai sandera.
    | Menahan pengajuan KRS menahan tindakan yang belum terjadi.
    |
    | Praktik "khs" lazim di Indonesia, jadi tetap disediakan. Pilihlah dengan
    | sadar.
    |
    */

    'gerbang' => env('EDOM_GERBANG', 'krs'),

    /*
    |--------------------------------------------------------------------------
    | Komentar Bebas
    |--------------------------------------------------------------------------
    |
    | Angka dapat dirata-ratakan sampai tidak seorang pun dapat dikenali.
    | Kalimat tidak: satu komentar tajam pada kelas berisi tujuh orang menunjuk
    | penulisnya lewat isinya sendiri, sekalipun namanya tidak pernah disimpan.
    |
    | Karena itu komentar mengikuti aturan yang lebih ketat daripada skor:
    |
    |   "prodi"  — hanya dapat dibaca pengelola program studi, tidak oleh dosen
    |              yang dinilai. Bawaan.
    |   "dosen"  — dosen dapat membacanya sendiri, di atas ambang responden.
    |   "tutup"  — tidak ditampilkan sama sekali; hanya skor.
    |
    */

    'komentar' => env('EDOM_KOMENTAR', 'prodi'),

];
