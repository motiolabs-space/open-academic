<?php

declare(strict_types=1);

return [

    'judul' => 'Klien SSO',
    'nav' => 'Klien SSO',
    'konteks' => ':aktif aplikasi boleh memakai akun kampus',

    'daftar' => [
        'judul' => 'Aplikasi Terdaftar',
        'nama' => 'Aplikasi',
        'redirect' => 'URI redirect',
        'pengguna' => 'Pengguna mengizinkan',
        'pengguna_hint' => 'Orang dengan izin yang masih dapat dipakai',
        'pihak_pertama' => 'Pihak pertama',
        'status' => 'Status',
        'aktif' => 'Aktif',
        'dicabut' => 'Dicabut',
        'dicabut_pada' => 'Dicabut :tanggal',
        'rotasi_terakhir' => 'Rahasia diganti :tanggal',
        'dari_env' => 'lewat SSO_FIRST_PARTY',
        'kosong' => 'Belum ada aplikasi terdaftar',
        'kosong_deskripsi' => 'Daftarkan aplikasi yang boleh mengenali pengguna lewat akun kampus, mis. Open Campus.',
    ],

    'form' => [
        'judul' => 'Daftarkan Aplikasi',
        'nama' => 'Nama aplikasi',
        'nama_hint' => 'Nama ini dibaca mahasiswa dan dosen pada layar persetujuan.',
        'redirect' => 'URI redirect',
        'redirect_hint' => 'Satu per baris. HTTPS; HTTP hanya untuk localhost.',
        'pihak_pertama' => 'Pihak pertama',
        'pihak_pertama_hint' => 'Lewati layar persetujuan',
        'pihak_pertama_peringatan' => 'Hanya untuk aplikasi yang dijalankan kampus sendiri. Melewati persetujuan menghapus satu-satunya titik di mana pengguna dapat menolak.',
        'simpan' => 'Daftarkan',
    ],

    'aksi' => [
        'rotasi' => 'Ganti rahasia',
        'rotasi_konfirmasi' => 'Ganti rahasia klien? Rahasia lama langsung tidak berlaku; aplikasi harus diperbarui sebelum pengguna berikutnya masuk.',
        'jadikan_pihak_pertama' => 'Lewati persetujuan',
        'jadikan_pihak_pertama_konfirmasi' => 'Izinkan aplikasi ini melewati layar persetujuan? Lakukan hanya untuk aplikasi milik kampus sendiri.',
        'minta_persetujuan' => 'Wajibkan persetujuan',
        'cabut' => 'Cabut',
        'cabut_konfirmasi' => 'Cabut aplikasi ini beserta seluruh tokennya? Semua pengguna langsung terputus, dan klien ini tidak dapat diaktifkan kembali.',
    ],

    'rahasia' => [
        'baru' => 'Rahasia klien untuk :nama (Client ID :id)',
        'dirotasi' => 'Rahasia baru untuk :nama (Client ID :id)',
        'sekali' => 'Salin sekarang ke konfigurasi aplikasi, bukan ke dalam kodenya. Rahasia disimpan ter-hash dan tidak akan ditampilkan lagi; bila hilang, ganti rahasia.',
        'sekali_rotasi' => 'Salin sekarang. Rahasia lama sudah tidak berlaku, dan yang ini tidak akan ditampilkan lagi.',
    ],

    'pesan' => [
        'pihak_pertama_disimpan' => 'Pengaturan persetujuan disimpan.',
        'dicabut' => ':nama dicabut; :jumlah token akses ikut dicabut.',
    ],

    'log' => [
        'rahasia_dirotasi' => 'Rahasia klien SSO diganti.',
        'dicabut' => 'Klien SSO dicabut beserta :jumlah token akses.',
    ],

    'galat' => [
        'nama_kosong' => 'Nama aplikasi wajib diisi.',
        'redirect_kosong' => 'Sekurang-kurangnya satu URI redirect wajib diisi.',
        'redirect_tidak_aman' => 'URI redirect ditolak: :uri. Kode otorisasi dikirim lewat URI ini; selain localhost, hanya HTTPS yang diterima.',
        'sudah_dicabut' => 'Klien ini sudah dicabut. Daftarkan aplikasi sebagai klien baru.',
    ],

];
