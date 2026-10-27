<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Buku Besar Angka Kredit (Kum A–E)
|--------------------------------------------------------------------------
|
| Semua nilai angka kredit (AK) dan SKS dalam PERSERATUS, sebagai integer —
| alasan yang sama dengan BKD_*_RATUS: angka ini dijumlahkan dari puluhan
| baris lalu dibandingkan dengan syarat jenjang, dan pembulatan float di
| sekitar 550,00 adalah beda antara siap mengusulkan dan belum.
|
| Buku besar ini mencatat dan menjumlah. Ia TIDAK menilai: penilaian PAK
| terjadi di platform kementerian. Angka di sini adalah bahan usulan, dan
| "siap" berarti "angkanya mencukupi syarat yang dikonfigurasi", bukan
| "akan disetujui". Lihat docs/KEPEGAWAIAN.md §Angka kredit dan ADR-028.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Konversi BKD → AK
    |--------------------------------------------------------------------------
    |
    | Kinerja sebelum 2026 dikonversi: SKS BKD × 4 = AK (ketentuan layanan PAK
    | 2026). Semester yang MULAI sebelum tanggal `sebelum` memakai faktor ini;
    | 20251 (Agustus 2025) masih terkonversi, 20252 (Februari 2026) tidak.
    |
    | Sesudahnya, kinerja diklaim langsung dari LKD/BKD di platform
    | kementerian. Buku besar tetap mencatatnya dengan `faktor_sesudah_ratus`
    | supaya kesiapan dapat diperkirakan — tetapi barisnya ditandai sebagai
    | PERKIRAAN di layar dan ekspor. Itu keputusan kampus, bukan ketentuan.
    |
    */

    'konversi' => [
        'sebelum' => env('ANGKA_KREDIT_KONVERSI_SEBELUM', '2026-01-01'),
        'faktor_ratus' => (int) env('ANGKA_KREDIT_FAKTOR_KONVERSI_RATUS', 400),   // × 4,00
        'faktor_sesudah_ratus' => (int) env('ANGKA_KREDIT_FAKTOR_SESUDAH_RATUS', 400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kum A — Pendidikan formal
    |--------------------------------------------------------------------------
    |
    | AK per ijazah, menurut kode jenjang PDDIKTI. Hanya ijazah yang lulus
    | SESUDAH tahun TMT jabatan berlaku yang masuk rekap — yang sebelumnya
    | sudah terhitung dalam AK kumulatif jabatan itu.
    |
    */

    'pendidikan_formal' => [
        '35' => (int) env('ANGKA_KREDIT_MAGISTER_RATUS', 15000), // S2      150
        '36' => (int) env('ANGKA_KREDIT_MAGISTER_RATUS', 15000), // S2 Terapan
        '40' => (int) env('ANGKA_KREDIT_DOKTOR_RATUS', 20000),   // S3      200
        '41' => (int) env('ANGKA_KREDIT_DOKTOR_RATUS', 20000),   // S3 Terapan
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas Kum D dan E
    |--------------------------------------------------------------------------
    |
    | Pengabdian dan penunjang masing-masing paling banyak 10 % dari total
    | DUPAK. Ditegakkan pada REKAP, bukan pada baris: buku besar menyimpan
    | yang sebenarnya dikerjakan, rekap menyajikan yang dapat diakui.
    |
    */

    'batas_persen' => [
        'pengabdian' => (int) env('ANGKA_KREDIT_BATAS_PENGABDIAN_PERSEN', 10),
        'penunjang' => (int) env('ANGKA_KREDIT_BATAS_PENUNJANG_PERSEN', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Syarat jenjang berikutnya
    |--------------------------------------------------------------------------
    |
    | Per jabatan TUJUAN: AK kumulatif minimum, dan bagian minimum Kum C
    | (penelitian) dari AK yang masih dibutuhkan untuk naik. Bawaan AK
    | kumulatif mengikuti JabatanFungsional::angkaKreditMinimum(); persentase
    | penelitian adalah tafsir pedoman yang kampus wajib periksa sendiri —
    | sikap yang sama dengan rubrik BKD.
    |
    */

    'syarat' => [
        'asisten_ahli' => [
            'kumulatif_ratus' => (int) env('ANGKA_KREDIT_SYARAT_AA_RATUS', 15000),
            'penelitian_persen' => (int) env('ANGKA_KREDIT_PENELITIAN_AA_PERSEN', 0),
        ],
        'lektor' => [
            'kumulatif_ratus' => (int) env('ANGKA_KREDIT_SYARAT_L_RATUS', 20000),
            'penelitian_persen' => (int) env('ANGKA_KREDIT_PENELITIAN_L_PERSEN', 35),
        ],
        'lektor_kepala' => [
            'kumulatif_ratus' => (int) env('ANGKA_KREDIT_SYARAT_LK_RATUS', 55000),
            'penelitian_persen' => (int) env('ANGKA_KREDIT_PENELITIAN_LK_PERSEN', 40),
        ],
        'guru_besar' => [
            'kumulatif_ratus' => (int) env('ANGKA_KREDIT_SYARAT_GB_RATUS', 85000),
            'penelitian_persen' => (int) env('ANGKA_KREDIT_PENELITIAN_GB_PERSEN', 45),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lembar penyetaraan
    |--------------------------------------------------------------------------
    |
    | Siapa yang masuk lembar AK penyetaraan: dosen tetap dengan jabatan
    | AA/L/LK yang TMT-nya paling lambat tanggal ini. Bahwa kampusnya PTNBH
    | atau PTS adalah syarat lembaga, bukan syarat per baris — tidak diperiksa.
    |
    */

    'penyetaraan' => [
        'tmt_paling_lambat' => env('ANGKA_KREDIT_PENYETARAAN_TMT', '2023-12-31'),
        'status_kepegawaian' => ['tetap'],
    ],

];
