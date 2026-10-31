<?php

declare(strict_types=1);

/*
 * The first-run setup wizard and the installation ping to Humanix (ADR-043).
 */
return [
    'judul' => 'Setup Awal Kampus',
    'pengantar' => 'Instalasi ini belum punya identitas institusi dan akun administrator. Isi lima langkah berikut sekali saja; setelah selesai, halaman ini tertutup permanen.',
    'langkah_ke' => 'Langkah :nomor dari :jumlah',
    'sebelumnya' => 'Sebelumnya',
    'berikutnya' => 'Berikutnya',
    'selesaikan' => 'Selesaikan Setup',
    'wajib_catatan' => 'Ruas bertanda * wajib diisi.',
    'galat_ringkas' => 'Ada isian yang perlu diperbaiki. Langkah yang bermasalah sudah dibukakan.',
    'logo_ulang' => 'Bila ada galat, pilih ulang berkas logo — peramban tidak menyimpan berkas yang sudah dipilih.',
    'selesai' => 'Setup selesai. Silakan masuk dengan akun super admin yang baru dibuat.',
    'admin_jabatan' => 'Administrator Sistem',

    'langkah' => [
        'identitas' => 'Identitas Perguruan Tinggi',
        'branding' => 'Warna & Logo',
        'admin' => 'Akun Super Admin',
        'struktur' => 'Struktur Awal',
        'persetujuan' => 'Tinjau & Selesaikan',
    ],

    'keterangan' => [
        'identitas' => 'Data ini tercetak pada kop dokumen dan menyertai setiap pelaporan PDDIKTI.',
        'branding' => 'Hanya dua warna yang dapat disesuaikan; sisanya mengikuti sistem desain agar tampilan tetap terbaca. Keduanya dapat diubah lagi di Pengaturan.',
        'admin' => 'Akun pertama dengan seluruh izin. Akun staf lain dibuat dari menu Kepegawaian setelah masuk.',
        'struktur' => 'Opsional. Lewati bila struktur kampus akan diisi dari Data Master atau ditarik dari Neo Feeder. Tanpa semester aktif, portal menampilkan "Semester Belum Dibuka" sampai semester dibuat di Data Master.',
        'persetujuan' => 'Periksa kembali, lalu tentukan apakah instalasi ini boleh dikabarkan ke pemelihara Open Academic.',
    ],

    'field' => [
        'nama_pt' => 'Nama perguruan tinggi',
        'singkatan' => 'Singkatan',
        'kode_pt' => 'Kode PT PDDIKTI',
        'alamat' => 'Alamat',
        'kota' => 'Kota/Kabupaten',
        'provinsi' => 'Provinsi',
        'surel' => 'Surel institusi',
        'telepon' => 'Telepon',
        'situs' => 'Situs web',
        'logo' => 'Logo',
        'warna_utama' => 'Warna utama',
        'warna_aksen' => 'Warna aksen',
        'admin_nama' => 'Nama lengkap',
        'admin_email' => 'Surel',
        'admin_nip' => 'NIP',
        'admin_password' => 'Kata sandi',
        'admin_password_confirmation' => 'Ulangi kata sandi',
        'fakultas_kode' => 'Kode fakultas',
        'fakultas_nama' => 'Nama fakultas',
        'prodi_kode' => 'Kode program studi',
        'prodi_nama' => 'Nama program studi',
        'prodi_jenjang' => 'Jenjang',
        'tahun_mulai' => 'Tahun mulai',
        'semester' => 'Semester',
        'tanggal_mulai' => 'Tanggal mulai',
        'tanggal_selesai' => 'Tanggal selesai',
    ],

    'hint' => [
        'singkatan' => 'Muncul pada judul tab dan kop transkrip.',
        'kode_pt' => 'Enam digit, sesuai PDDIKTI. Salah isi berarti pelaporan kampus ini tercatat atas nama institusi lain.',
        'logo' => 'PNG, JPG, atau WebP, paling besar 1 MB.',
        'warna' => 'Kode heks enam digit, misalnya #1E2761.',
        'admin_email' => 'Dipakai untuk masuk dan menerima pemberitahuan sistem.',
        'admin_password' => 'Minimal 8 karakter; di produksi minimal 10 dengan huruf dan angka.',
        'fakultas' => 'Fakultas pertama',
        'prodi' => 'Program studi pertama (memerlukan fakultas)',
        'semester' => 'Semester berjalan — langsung diaktifkan',
    ],

    'ringkasan' => [
        'judul' => 'Ringkasan',
        'institusi' => 'Institusi',
        'admin' => 'Super admin',
        'struktur' => 'Struktur awal',
        'kosong' => '—',
    ],

    'validasi' => [
        'kode_pt' => 'Kode PT PDDIKTI terdiri atas enam digit angka, misalnya 001001.',
        'warna' => 'Warna harus berupa kode heks enam digit, misalnya #1E2761.',
        'admin_email_unik' => 'Surel ini sudah dipakai akun staf lain.',
        'prodi_tanpa_fakultas' => 'Program studi memerlukan fakultas; isi nama fakultasnya juga.',
        'tanggal_selesai' => 'Semester harus berakhir setelah tanggal mulainya.',
    ],

    'log' => [
        'setup_selesai' => 'Setup awal selesai untuk :pt; super admin pertama :admin.',
        'setup_dibuka_ulang' => 'Setup awal dibuka ulang lewat artisan.',
        'telemetri_aktif' => 'Notifikasi instalasi ke Humanix diaktifkan.',
        'telemetri_nonaktif' => 'Notifikasi instalasi ke Humanix dimatikan.',
    ],

    'telemetri' => [
        'judul' => 'Notifikasi Instalasi ke Humanix',
        'meta' => 'opsional · bawaan mati',
        'pengantar' => 'Open Academic gratis dan berlisensi MIT, sehingga pemeliharanya tidak tahu kampus mana yang memakainya kecuali diberi tahu. Bila Anda setuju, instalasi ini mengirim satu kabar singkat ke :tujuan saat pertama dipasang dan setiap kali versinya berubah — tidak pernah berkala.',
        'yang_dikirim' => 'Yang dikirim, dan hanya ini:',
        'daftar' => [
            'installation_id' => 'ID instalasi acak (UUID) — dibuat sekali, tidak diturunkan dari data kampus',
            'event' => 'Jenis kabar: dipasang atau diperbarui',
            'institution_name' => 'Nama perguruan tinggi',
            'institution_code' => 'Kode PT PDDIKTI',
            'city' => 'Kota/kabupaten',
            'province' => 'Provinsi',
            'app_version' => 'Versi Open Academic',
            'php_version' => 'Versi PHP (mayor.minor)',
            'db_engine' => 'Jenis mesin basis data (mysql, mariadb, …)',
            'date' => 'Tanggal pengiriman',
        ],
        'tidak_dikirim' => 'Tidak pernah dikirim: data mahasiswa, dosen, atau staf; kata sandi dan kredensial apa pun; APP_KEY; alamat server internal.',
        'setuju' => 'Saya setuju instalasi ini mengirim data di atas ke :tujuan.',
        'sertakan_url' => 'Sertakan juga alamat aplikasi ini (:url).',
        'sertakan_url_hint' => 'Tambahan opsional. Biarkan kosong bila alamat ini hanya dapat diakses dari jaringan kampus.',
        'matikan_kapan_saja' => 'Dapat dimatikan kapan saja di Pengaturan, atau untuk selamanya dengan HUMANIX_TELEMETRI=false pada .env. Rinciannya di docs/TELEMETRI.md.',
        'dimatikan_env' => 'Dimatikan lewat .env (HUMANIX_TELEMETRI=false). Tidak ada yang dikirim, apa pun pilihan di layar ini.',
        'simpan' => 'Simpan Pilihan',
        'disimpan' => 'Pilihan notifikasi instalasi disimpan.',
        'pratinjau' => 'Isi kabar yang akan dikirim saat ini',
        'id_belum_ada' => '(dibuat saat pengiriman pertama)',
        'riwayat' => 'Riwayat pengiriman',
        'riwayat_kosong' => 'Belum ada kabar yang pernah dikirim dari instalasi ini.',

        'keadaan' => [
            'aktif' => 'Aktif',
            'nonaktif' => 'Mati',
            'env' => 'Dimatikan .env',
        ],

        'kolom' => [
            'versi' => 'Versi',
            'peristiwa' => 'Kabar',
            'status' => 'Status',
            'percobaan' => 'Percobaan',
            'waktu' => 'Terkirim',
            'isi' => 'Isi',
        ],

        'peristiwa' => [
            'installed' => 'Dipasang',
            'upgraded' => 'Diperbarui',
        ],

        'status' => [
            'queued' => 'Antre',
            'sent' => 'Terkirim',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
        ],
    ],
];
