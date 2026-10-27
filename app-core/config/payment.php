<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Perilaku Tagihan
|--------------------------------------------------------------------------
|
| Berkas ini sengaja hanya memuat yang benar-benar dibaca kode.
|
| Sebelumnya ia juga menjanjikan pemilihan gateway — `gateway` (bawaan "fake"),
| `midtrans` beserta kunci merchant dan daftar kanal pembayaran, dan
| `currency`. Tidak satu pun pernah dibaca: nol pemanggil, tidak ada kelas
| driver, tidak ada binding. Config yang menjanjikan sesuatu yang tidak ada
| lebih buruk daripada tidak ada config — seseorang akan menyetel
| PAYMENT_GATEWAY=midtrans, mengisi kunci merchantnya, lalu menunggu sesuatu
| terjadi.
|
| **Pembayaran sebagian.** Dulu ada `invoice.allow_partial` di sini, juga tanpa
| pembaca — padahal perilakunya MEMANG berjalan: `PembayaranService` menandai
| tagihan `InvoiceStatus::Sebagian` begitu ada pembayaran yang kurang dari
| totalnya. Kampus yang menyetelnya `false` tetap menerima cicilan tanpa
| pemberitahuan apa pun. Benderanya dicabut, bukan disambungkan —
| menyambungkannya berarti mengubah perilaku keuangan yang sudah berjalan atas
| dasar tebakan. Kalau memang dibutuhkan, ia layak jadi permintaan fitur dengan
| aturannya sendiri: apa yang terjadi pada tagihan yang sudah separuh terbayar
| ketika benderanya dimatikan?
|
| Kontrak gateway yang sungguhan hidup di
| `app/Services/Keuangan/Contracts/PaymentGatewayInterface.php`. Ketika
| adaptornya dibangun — tertahan pada kredensial merchant, lihat ROADMAP
| §Menunggu di Luar Repo — konfigurasinya kembali ke sini BERSAMA kodenya,
| bukan mendahuluinya.
|
*/

return [

    'invoice' => [
        // Berapa hari tagihan semester tetap dapat dibayar sesudah terbit.
        'due_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Denda keterlambatan
    |--------------------------------------------------------------------------
    |
    | Kebijakan kampus, bukan fakta — maka mati secara bawaan. Kampus yang
    | menyalakannya memilih bentuknya:
    |
    |   bentuk=nominal : `nominal` rupiah per periode keterlambatan;
    |   bentuk=persen  : `persen` dari sisa tagihan pokok (tanpa denda) per
    |                    periode, dibulatkan ke bawah ke rupiah penuh.
    |
    | `periode` hari atau minggu; minggu yang baru berjalan dihitung penuh.
    | `maksimum` membatasi denda kumulatif satu tagihan (0 = tanpa batas).
    | `tenggang_hari` adalah hari sesudah jatuh tempo yang masih bebas denda.
    |
    | Denda dihitung oleh `openacademic:hitung-denda` yang terjadwal harian dan
    | hanya menambah periode yang belum pernah dihitung, jadi menjalankannya
    | dua kali pada hari yang sama tidak menggandakan apa pun. Jalankan dengan
    | `--kering` sebelum menyalakannya: tagihan yang sudah lama menunggak akan
    | dikenai denda sejak jatuh temponya, bukan sejak hari penyalaan.
    |
    | Denda TIDAK memperketat gerbang KRS: ambang pembayaran dihitung terhadap
    | tagihan pokok. Lihat docs/MODUL.md §Keuangan.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Tagihan dari KRS (tarif per SKS dan per mata kuliah)
    |--------------------------------------------------------------------------
    |
    | Komponen `per_sks` dan `mata_kuliah` di matriks tarif tidak ikut terbit
    | massal: ditagih ketika Dosen Wali menyetujui KRS, ke tagihan semester yang
    | sama. Lihat ADR-030.
    |
    | `jatuh_tempo_hari` — hari sesudah persetujuan KRS. Bila tagihan semester
    | jatuh tempo lebih lambat, tanggal itu yang dipakai; tidak pernah dimajukan.
    |
    | `batas_pengurangan` — sampai kapan kelas yang dibatalkan saat revisi KRS
    | masih mengurangi tagihan:
    |   krs_selesai           : sampai penutupan pengisian KRS reguler (bawaan);
    |   krs_perubahan_selesai : sampai penutupan masa perubahan KRS;
    |   tidak_pernah          : pembatalan tidak pernah mengurangi tagihan.
    | Pembatalan sesudah batas tetap dicatat sebagai baris Rp0 di tagihan,
    | supaya mahasiswa melihat kenapa angkanya tidak turun.
    |
    */

    'krs' => [
        'jatuh_tempo_hari' => (int) env('PAYMENT_KRS_JATUH_TEMPO_HARI', 14),
        'batas_pengurangan' => env('PAYMENT_KRS_BATAS_PENGURANGAN', 'krs_selesai'),
    ],

    'denda' => [
        'aktif' => (bool) env('PAYMENT_DENDA_AKTIF', false),
        'bentuk' => env('PAYMENT_DENDA_BENTUK', 'nominal'),
        'periode' => env('PAYMENT_DENDA_PERIODE', 'hari'),
        'nominal' => (int) env('PAYMENT_DENDA_NOMINAL', 10_000),
        'persen' => (int) env('PAYMENT_DENDA_PERSEN', 1),
        'maksimum' => (int) env('PAYMENT_DENDA_MAKSIMUM', 500_000),
        'tenggang_hari' => (int) env('PAYMENT_DENDA_TENGGANG_HARI', 0),
    ],

];
