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

];
