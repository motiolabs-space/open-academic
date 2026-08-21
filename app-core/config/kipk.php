<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | KIP Kuliah — Pelaporan Semester
    |--------------------------------------------------------------------------
    |
    | Puslapdik meminta laporan per semester atas setiap penerima: masih aktif
    | atau tidak, berapa SKS ditempuh, berapa IPS dan IPK-nya. Semuanya sudah
    | tersimpan di `status_mahasiswa` sebagai efek samping menjalankan semester.
    |
    | Yang TIDAK tersimpan adalah penandanya: aplikasi ini tidak tahu skema
    | beasiswa mana yang KIP Kuliah. Tabel `beasiswa` hanya membedakan internal
    | dan eksternal, dan setiap kampus memberi kodenya sendiri.
    |
    */

    /*
    | Kode skema pada tabel `beasiswa` yang merupakan KIP Kuliah.
    |
    | Sengaja KOSONG secara bawaan. Menebak kode seperti "KIPK" akan menghasilkan
    | berkas berisi nol penerima pada kampus yang memakai kode lain — dan berkas
    | nol penerima terbaca sebagai "tidak ada yang menerima KIP Kuliah", bukan
    | sebagai "skemanya belum ditetapkan". Selama kosong, ekspornya menolak
    | berjalan dan layarnya mengatakan sebabnya.
    |
    | Diisi lewat .env, dipisah koma:
    |
    |     KIPK_BEASISWA_KODE=BS-KIP
    |     KIPK_BEASISWA_KODE=BS-KIP,BS-KIP-AFIRMASI
    |
    | Lewat .env dan bukan dengan menyunting berkas ini, karena berkas ini
    | terlacak Git: kampus yang mengubahnya akan bertabrakan setiap kali menarik
    | pembaruan, dan konflik semacam itu biasanya diselesaikan dengan mengambil
    | versi hulu — yang diam-diam mengosongkan kembali skemanya.
    */
    'beasiswa_kode' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('KIPK_BEASISWA_KODE', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Yang Sengaja Tidak Diekspor
    |--------------------------------------------------------------------------
    |
    | KIP Kuliah berbasis kemampuan ekonomi, jadi penghasilan orang tua dan
    | alamat rumah adalah data yang paling menggoda untuk ikut disertakan.
    | Keduanya tidak pernah masuk berkas ini.
    |
    | Alasannya sama dengan yang menahan NIK keluar dari muatan Bridge: berkas
    | CSV beredar lewat surel dan folder bersama, dan itu saluran yang berbeda
    | dari pengiriman resmi ke Puslapdik. Muatan yang aman di satu saluran dan
    | tidak di saluran lain pada akhirnya bocor lewat yang ceroboh.
    |
    | Yang dibawa hanyalah NIM sebagai pengenal — konvensi yang sama dipakai
    | kontak akuntansi.
    |
    */

];
