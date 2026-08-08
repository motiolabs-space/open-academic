<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | Integrasi akuntansi bersifat **opsional dan mati sampai dinyalakan.**
    | Banyak kampus memegang buku besarnya di tempat lain, atau mengerjakannya
    | secara manual, dan mereka tidak boleh menanggung apa pun dari modul ini.
    |
    |   nonaktif  Tidak ada yang dicatat. Tidak ada baris outbox, tidak ada menu
    |             Akuntansi, tidak ada perintah terjadwal yang berjalan.
    |             Penagihan di Open Academic sama sekali tidak terpengaruh.
    |
    |   palsu     Dokumen diantre dan dapat diekspor, tetapi tidak ada yang
    |             dikirim. Keadaan untuk mencoba modulnya, dan yang dipakai
    |             kampus demo serta test suite.
    |
    |   easyerp   Terhubung sungguhan ke API v1 Easy Accounting.
    |
    | Bawaannya "nonaktif". Instalasi baru tidak boleh diam-diam mulai
    | menumpuk dokumen untuk sistem yang belum tentu dipakai kampusnya —
    | dan memposting jurnal ke buku besar sungguhan jelas bukan sesuatu yang
    | boleh terjadi tanpa seseorang memutuskannya.
    |
    */

    'driver' => env('AKUNTANSI_DRIVER', 'nonaktif'), // nonaktif | palsu | easyerp

    /*
    |--------------------------------------------------------------------------
    | Sambungan easyERP
    |--------------------------------------------------------------------------
    |
    | API Key diterbitkan per tenant di Pengaturan Usaha → Integrasi pada sisi
    | easyERP. Satu kunci = satu badan hukum; kampus dengan beberapa yayasan
    | memerlukan instalasi terpisah, bukan kunci kedua di sini.
    |
    */

    'easyerp' => [
        'base_url' => env('AKUNTANSI_BASE_URL', 'http://localhost/easyerp/public/api/v1'),
        'api_key' => env('AKUNTANSI_API_KEY'),
        'timeout' => (int) env('AKUNTANSI_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kode Akun (COA)
    |--------------------------------------------------------------------------
    |
    | **Kebijakan kampus, bukan fakta.** Bagan akun berbeda antar perguruan
    | tinggi, dan yang di bawah hanya bawaan yang lazim. Sikap yang sama dengan
    | bobot SKS di config/bkd.php: Open Academic menjamin angkanya benar dan
    | menyerahkan ke akun mana ia dibukukan.
    |
    | Invoice tidak memerlukan kode akun — easyERP menurunkannya sendiri dari
    | sub_type Receivable dan type revenue. Yang di bawah dipakai untuk jurnal
    | yang kita susun sendiri: penerimaan kas dan beban beasiswa.
    |
    */

    'akun' => [
        'kas' => env('AKUNTANSI_AKUN_KAS', '1-1001'),
        'bank' => env('AKUNTANSI_AKUN_BANK', '1-1002'),
        'piutang' => env('AKUNTANSI_AKUN_PIUTANG', '1-1201'),
        'pendapatan' => env('AKUNTANSI_AKUN_PENDAPATAN', '4-1001'),
        'beban_beasiswa' => env('AKUNTANSI_AKUN_BEASISWA', '6-1101'),
    ],

    /*
    | Channel pembayaran mana yang masuk ke kas dan mana ke bank.
    |
    | Salah menaruhnya tidak membuat neraca timpang — keduanya aset — tetapi
    | membuat rekonsiliasi bank mustahil, karena setoran yang tidak pernah
    | menyentuh rekening muncul di sana.
    */
    'channel_kas' => ['tunai'],

    /*
    |--------------------------------------------------------------------------
    | Perlakuan Akuntansi
    |--------------------------------------------------------------------------
    */

    'perlakuan' => [
        /*
         * Beasiswa dan keringanan: "bruto" atau "netto".
         *
         * Bruto mengakui pendapatan sebesar tarif penuh lalu membukukan
         * potongannya sebagai Beban Beasiswa. Laporan laba rugi kemudian
         * menunjukkan berapa yang benar-benar dikeluarkan kampus — angka yang
         * dicari yayasan dan pemberi hibah, dan yang lenyap sama sekali pada
         * perlakuan netto (yang hanya memperlihatkan pendapatan lebih kecil,
         * tanpa sebab).
         */
        'beasiswa' => env('AKUNTANSI_PERLAKUAN_BEASISWA', 'bruto'), // bruto | netto

        /*
         * PPN atas tagihan kuliah.
         *
         * Jasa pendidikan dikecualikan dari PPN, jadi bawaannya false dan
         * easyERP tidak akan membentuk PPN Keluaran. Kampus yang menagih
         * komponen kena pajak lewat badan yang sama perlu menyalakannya — dan
         * memastikan akun "PPN Keluaran" ada di sana lebih dulu.
         */
        'kena_ppn' => (bool) env('AKUNTANSI_KENA_PPN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengiriman
    |--------------------------------------------------------------------------
    |
    | Dokumen ditulis ke outbox lebih dulu, lalu dikirim terpisah. Penerbitan
    | tagihan untuk lima ribu mahasiswa tidak boleh menunggu lima ribu panggilan
    | HTTP, dan easyERP yang sedang mati tidak boleh menggagalkan penagihan.
    |
    */

    /*
    | Ekspor CSV.
    |
    | Punya kuncinya sendiri alih-alih meminjam milik config/bkd.php: keduanya
    | kebetulan sama nilainya, dan kunci pinjaman adalah cara sebuah modul
    | berubah perilaku karena modul lain disetel ulang.
    */
    'ekspor' => [
        // Excel di Windows berbahasa Indonesia membaca CSV UTF-8 tanpa BOM
        // sebagai mojibake.
        'bom_utf8' => (bool) env('AKUNTANSI_CSV_BOM', true),
    ],

    'pengiriman' => [
        // Dokumen per sekali jalan. Ditahan supaya satu perintah tidak memegang
        // koneksi selama belasan menit saat antrean menumpuk.
        'ukuran_batch' => (int) env('AKUNTANSI_BATCH', 100),

        // Menyerah setelah sekian percobaan; sesudahnya perlu tindakan manusia.
        // Dokumen yang terus dicoba selamanya menyembunyikan kesalahan pemetaan
        // akun di balik angka percobaan yang membesar.
        'maks_percobaan' => (int) env('AKUNTANSI_MAKS_PERCOBAAN', 5),

        // Jeda dasar backoff, dalam menit: 1, 2, 4, 8, 16.
        'backoff_menit' => (int) env('AKUNTANSI_BACKOFF_MENIT', 1),
    ],

];
