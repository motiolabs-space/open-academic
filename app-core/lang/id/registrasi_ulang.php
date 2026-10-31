<?php

declare(strict_types=1);

// Re-NIM, readmission and reactivation (ROADMAP #55, ADR-042 §3).
return [

    'judul' => 'Registrasi Ulang (re-NIM)',
    'tombol' => 'Pindah prodi / masuk kembali',
    'penjelasan' => 'Catat peristiwa yang mengubah registrasi mahasiswa ini. Pindah prodi dan masuk kembali membuka '
        .'registrasi baru dengan NIM baru — akun, NIM, nilai, dan riwayat PDDIKTI yang lama tetap utuh dan saling tertaut. '
        .'Aktif kembali membuka ulang registrasi yang sama dengan NIM yang sama. Tidak ada NIM yang ditimpa.',
    'tidak_ada_jenis' => 'Tidak ada peristiwa registrasi ulang yang dapat dicatat untuk mahasiswa berstatus :status.',

    'jenis' => [
        'pindah_prodi' => 'Pindah program studi',
        'masuk_kembali' => 'Masuk kembali',
        'aktif_kembali' => 'Aktif kembali',
    ],

    'form' => [
        'judul' => 'Catat peristiwa',
        'registrasi_baru' => 'Registrasi baru',
        'registrasi_baru_hint' => 'Diisi untuk pindah prodi dan masuk kembali; diabaikan untuk aktif kembali.',
        'nim_hint' => 'Kosongkan untuk NIM dari pola prodi tujuan.',
        'email_hint' => 'Surel akun baru; harus berbeda dari akun lama.',
        'alasan_hint' => 'Untuk arsip kampus; tidak dikirim ke PDDIKTI.',
        'simpan' => 'Catat',
        'konfirmasi' => 'Catat peristiwa registrasi ini? Peristiwa tidak dapat disunting sesudah dicatat.',
        'atribut' => [
            'jenis' => 'Peristiwa',
            'term' => 'Semester berlaku',
            'tanggal' => 'Tanggal berlaku',
            'prodi' => 'Program studi tujuan',
            'nim' => 'NIM baru',
            'email' => 'Surel akun baru',
            'nomor_sk' => 'Nomor SK',
            'tanggal_sk' => 'Tanggal SK',
            'alasan' => 'Alasan',
        ],
    ],

    'riwayat' => [
        'judul' => 'Riwayat registrasi',
        'kosong' => 'Belum pernah pindah prodi, masuk kembali, atau diaktifkan kembali.',
        'dari' => 'Registrasi sebelumnya:',
        'ke' => 'Registrasi baru:',
        'status' => ':lama → :baru',
    ],

    'pesan' => [
        'pindah_prodi' => 'Pindah prodi dicatat. NIM :lama ditutup; registrasi baru :baru dibuka.',
        'masuk_kembali' => 'Masuk kembali dicatat. Registrasi baru :baru dibuka; registrasi :lama tetap tertutup.',
        'aktif_kembali' => 'Mahasiswa :lama diaktifkan kembali dengan NIM yang sama.',
    ],

    'log' => [
        'pindah_prodi' => 'Pindah program studi pada :term: NIM :lama ditutup, registrasi baru :baru dibuka oleh :oleh.',
        'masuk_kembali' => 'Masuk kembali pada :term: registrasi baru :baru dibuka dari :lama oleh :oleh.',
        'aktif_kembali' => 'Aktif kembali pada :term dengan NIM :lama, dicatat oleh :oleh.',
    ],

    'galat' => [
        'status' => ':jenis tidak dapat dicatat untuk mahasiswa berstatus :status.',
        'prodi_kosong' => 'Pilih program studi untuk registrasi baru.',
        'prodi_sama' => 'Pindah prodi harus ke program studi lain.',
        'email_dipakai' => 'Surel ":email" kosong atau sudah dipakai akun mahasiswa lain.',
        'nim_dipakai' => 'NIM :nim sudah dipakai.',
        'sudah_dialihkan' => 'Registrasi :nim sudah pernah dialihkan ke registrasi baru. Catat peristiwa berikutnya dari registrasi terbarunya.',
        'asal_internal' => 'Jenis daftar dan asal registrasi ini berasal dari peristiwa pindah prodi atau masuk kembali, dan tidak disunting di layar pindahan.',
    ],

    'feeder' => [
        'judul' => 'Yang dikirim ke PDDIKTI',
        'pindah_prodi' => 'Registrasi lama ditutup sebagai Lulus/DO dengan jenis keluar yang dipetakan untuk status Ganti Prodi. '
            .'Registrasi baru dikirim sebagai riwayat pendidikan baru pada orang yang sama, jenis daftar "Pindah program studi" '
            .'yang dipetakan dari referensi Feeder, asal kampus ini.',
        'masuk_kembali' => 'Registrasi lama tetap tertutup. Registrasi baru dikirim sebagai riwayat pendidikan baru pada orang yang sama, '
            .'jenis daftar "Masuk kembali" yang dipetakan dari referensi Feeder.',
        'aktif_kembali' => 'Registrasi yang sama dibuka kembali. Bila keluarnya sudah dilaporkan, permintaan PJK terbuka di konsol Feeder: '
            .'pembatalan keluar diajukan lewat PDDIKTI admin, bukan dihapus dari sini.',
        'pindah_tidak_tercatat' => 'Status Ganti Prodi tanpa peristiwa pindah prodi yang tercatat. Catat lewat Registrasi Ulang supaya tanggal dan semester keluarnya diketahui.',
        'sks_diakui_kosong' => 'Belum ada SKS yang diakui dari registrasi sebelumnya; riwayat pendidikan dikirim dengan 0 SKS diakui. Akui mata kuliah lewat layar konversi bila ada.',
    ],

];
