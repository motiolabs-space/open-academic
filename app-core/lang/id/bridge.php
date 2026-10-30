<?php

declare(strict_types=1);

return [

    'kelola' => 'Kelola',

    'daftar' => [
        'judul' => 'Daftarkan Aplikasi',
        'nama' => 'Nama aplikasi',
        'slug' => 'Slug',
        'slug_hint' => 'Huruf kecil, angka, dan tanda hubung, mis. open-campus. Tidak dapat diubah kemudian.',
        'deskripsi' => 'Keterangan',
        'base_url' => 'Alamat aplikasi',
        'scopes' => 'Scope yang boleh dibaca',
        'aktif' => 'Aktif',
        'aktif_hint' => 'Konsumen nonaktif ditolak di setiap permintaan, apa pun tokennya',
        'simpan' => 'Daftarkan',
        'perbarui' => 'Simpan perubahan',
        'kosong' => 'Daftarkan aplikasi konsumen, lalu terbitkan tokennya dari halaman kelolanya.',
    ],

    'konsumen' => [
        'data' => 'Data Aplikasi',
        'nonaktif' => 'Aplikasi ini nonaktif: setiap permintaannya ditolak, dan token baru tidak dapat diterbitkan.',
    ],

    'token' => [
        'judul' => 'Token Akses',
        'nama' => 'Nama token',
        'nama_hint' => 'Untuk membedakan beberapa token, mis. produksi atau staging.',
        'dibuat' => 'Diterbitkan',
        'terakhir' => 'Terakhir dipakai',
        'belum_dipakai' => 'Belum pernah',
        'terbitkan' => 'Terbitkan token',
        'cabut' => 'Cabut',
        'cabut_konfirmasi' => 'Cabut token ini? Aplikasi yang memakainya langsung ditolak.',
        'kosong' => 'Belum ada token aktif.',
        'baru' => 'Token baru: :nama',
        'sekali' => 'Salin sekarang dan simpan di konfigurasi aplikasi konsumen. Token ini tidak disimpan dalam bentuk yang dapat dibaca dan tidak akan ditampilkan lagi; bila hilang, cabut lalu terbitkan yang baru.',
        'hanya_kredensial' => 'Menerbitkan dan mencabut token memerlukan izin kredensial integrasi.',
    ],

    'webhook' => [
        'judul' => 'Webhook',
        'url' => 'URL penerima',
        'url_hint' => 'HTTPS. Kosongkan untuk berhenti mengirim event.',
        'events' => 'Event yang dikirim',
        'simpan' => 'Simpan webhook',
        'rahasia' => 'Rahasia penandatangan',
        'rahasia_ada' => 'Tersimpan terenkripsi. Tidak dapat ditampilkan ulang.',
        'rahasia_belum' => 'Belum ada. Pengiriman memakai BRIDGE_WEBHOOK_SECRET bila diisi, dan dibatalkan bila tidak.',
        'rotasi' => 'Buat rahasia baru',
        'rotasi_konfirmasi' => 'Buat rahasia baru? Rahasia lama langsung tidak berlaku; aplikasi konsumen harus diperbarui sebelum event berikutnya.',
        'rahasia_baru' => 'Rahasia penandatangan webhook yang baru',
        'rahasia_sekali' => 'Salin sekarang ke konfigurasi penerima webhook. Rahasia ini tidak akan ditampilkan lagi; rahasia lama sudah tidak berlaku.',
    ],

    'riwayat' => [
        'judul' => 'Riwayat',
        'kosong' => 'Belum ada catatan.',
    ],

    'pesan' => [
        'didaftarkan' => ':nama didaftarkan. Terbitkan token untuk mulai terhubung.',
        'diperbarui' => 'Data aplikasi diperbarui.',
        'token_dicabut' => 'Token dicabut. Permintaan yang memakainya kini ditolak.',
        'webhook_disimpan' => 'Pengaturan webhook disimpan.',
    ],

    'log' => [
        'token_diterbitkan' => 'Token ":nama" diterbitkan.',
        'token_dicabut' => 'Token ":nama" dicabut.',
        'token_dicabut_semua' => ':jumlah token dicabut.',
        'rahasia_dirotasi' => 'Rahasia penandatangan webhook diganti.',
    ],

    'galat' => [
        'slug_dipakai' => 'Slug ini sudah dipakai aplikasi lain, termasuk yang sudah dihapus.',
        'konsumen_nonaktif' => 'Aplikasi ini nonaktif. Aktifkan lebih dulu sebelum menerbitkan token.',
        'scope_tidak_dikenal' => 'Scope tidak dikenal: :scope.',
        'event_tidak_dikenal' => 'Event tidak dikenal: :event.',
        'url_tidak_aman' => 'URL webhook harus HTTPS (HTTP hanya untuk localhost).',
    ],

    'validasi' => [
        'slug' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
    ],

];
