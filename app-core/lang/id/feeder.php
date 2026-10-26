<?php

declare(strict_types=1);

return [

    'perubahan' => [
        'judul' => 'Perubahan Data Mahasiswa & Jenis Keluar',
        'meta' => ':terbuka harus diajukan · :selesai selesai',
        'penjelasan' => 'Enam kolom identitas dan tujuh kolom keluar dikunci PDDIKTI setelah tersinkron. '
            .'Perubahan di bawah ini tercatat saat terjadi; biodata mahasiswanya tidak dikirim ulang sampai permintaannya selesai.',
        'kosong' => [
            'judul' => 'Tidak ada kolom terkunci yang berubah',
            'deskripsi' => 'Begitu NIM, nama, nama ibu, tempat atau tanggal lahir, periode masuk, atau data keluar seorang mahasiswa yang sudah tersinkron diubah, permintaannya muncul di sini.',
        ],
        'kolom' => [
            'mahasiswa' => 'Mahasiswa',
            'jenis' => 'Jenis',
            'kolom' => 'Kolom',
            'perubahan' => 'Perubahan',
            'jalur' => 'Jalur',
            'status' => 'Status',
        ],
        'jalur' => [
            'web_service' => 'Dapat dikirim lewat web service',
            'pddikti_admin' => 'Harus diajukan lewat PDDIKTI admin',
        ],
        'tombol' => [
            'ajukan' => 'Tandai diajukan',
            'kirim' => 'Kirim lewat web service',
            'selesai' => 'Tandai selesai',
        ],
        'nomor_surat' => 'Nomor surat permohonan',
        'kosong_nilai' => '(kosong)',
        'surat' => 'Surat :nomor',
        'gagal' => 'Ditolak Feeder: :sebab',
        'pesan' => [
            'diajukan' => 'Perubahan :kolom untuk :nim ditandai sudah diajukan ke PDDIKTI admin.',
            'selesai' => 'Perubahan :kolom untuk :nim ditandai selesai; biodata mahasiswa dapat disinkronkan kembali.',
            'terkirim' => 'Perubahan :kolom untuk :nim terkirim lewat UpdateRiwayatPendidikanMahasiswa.',
            'ditolak' => 'Perubahan :kolom ditolak Feeder: :sebab. Ajukan lewat PDDIKTI admin.',
        ],
    ],

    'adopsi' => [
        'judul' => 'Adopsi Mahasiswa dari PDDIKTI',
        'konteks' => 'Mahasiswa yang sudah terdaftar sebelum kampus memakai Open Academic',
        'tautan_konsol' => 'Adopsi Mahasiswa',
        'meta' => ':jumlah baris',
        'penjelasan' => 'Kampus yang pindah dari SIAKAD lain sudah punya mahasiswanya di PDDIKTI. Adopsi membaca daftar mahasiswa '
            .'dan riwayat pendidikan dari Feeder per prodi dan periode masuk, mencocokkannya lewat NIM lalu NIK, dan mencatat id Feeder-nya '
            .'di sini — tanpa mengirim apa pun. Sinkronisasi berikutnya melewati mereka, bukan mendaftarkannya ulang.',
        'privasi' => 'NIK hanya dibandingkan, tidak disimpan dan tidak ditampilkan.',
        'form' => [
            'prodi' => 'Program studi',
            'periode' => 'Periode masuk',
            'periode_hint' => 'Kode PDDIKTI, mis. 20241 untuk 2024/2025 Ganjil.',
            'jalankan' => 'Jalankan adopsi',
            'tampilkan' => 'Tampilkan',
        ],
        'ringkasan' => [
            'diadopsi' => 'Cocok & diadopsi',
            'hanya_feeder' => 'Hanya di Feeder',
            'hanya_lokal' => 'Hanya di SIAKAD',
            'ambigu' => 'Ambigu',
            'diputuskan' => 'Diputuskan manual',
        ],
        'status' => [
            'diadopsi' => 'Cocok & diadopsi',
            'hanya_feeder' => 'Hanya di Feeder',
            'hanya_lokal' => 'Hanya di SIAKAD',
            'ambigu' => 'Ambigu',
            'ditautkan' => 'Ditautkan manual',
            'dipisahkan' => 'Dinyatakan berbeda',
        ],
        'alasan' => [
            'kandidat_ganda' => 'Lebih dari satu data Feeder cocok lewat NIM atau NIK.',
            'nik_berbeda' => 'NIM cocok, tetapi NIK di Feeder berbeda.',
            'nim_berbeda' => 'NIK cocok, tetapi terdaftar di Feeder dengan NIM lain.',
            'nama_berbeda' => 'NIM cocok, NIK tidak tersedia di salah satu sisi, dan namanya berbeda.',
            'tertaut_lain' => 'Mahasiswa ini sudah tertaut ke data Feeder yang lain.',
        ],
        'kolom' => [
            'status' => 'Status',
            'lokal' => 'Di SIAKAD',
            'feeder' => 'Di Feeder',
            'keterangan' => 'Keterangan',
        ],
        'kandidat' => [
            'nim' => 'NIM :nim',
            'lahir' => 'lahir :tanggal',
            'nik_cocok' => 'NIK sama',
            'nik_beda' => 'NIK berbeda',
            'nik_tidak_ada' => 'NIK tidak dapat dibandingkan',
        ],
        'nama_berbeda' => 'Nama di Feeder: :nama',
        'keputusan' => 'Diputuskan :nama pada :tanggal: :catatan',
        'catatan' => 'Alasan keputusan',
        'catatan_placeholder' => 'Mis. dicocokkan dengan ijazah SMA dan KTP pada berkas PMB',
        'tombol' => [
            'tautkan' => 'Tautkan ke kandidat ini',
            'pisahkan' => 'Bukan orang yang sama',
        ],
        'kosong' => [
            'judul' => 'Belum ada hasil adopsi',
            'deskripsi' => 'Pilih program studi dan periode masuk, lalu jalankan adopsi. Kampus yang memulai dari nol di Open Academic tidak memerlukannya.',
        ],
        'pesan' => [
            'selesai' => ':prodi :periode: :diadopsi diadopsi, :sudah sudah tertaut, :lokal hanya di SIAKAD, :feeder hanya di Feeder, :ambigu ambigu.',
            'ditautkan' => 'Mahasiswa :nim ditautkan ke registrasi Feeder dengan NIM :nim_feeder.',
            'dipisahkan' => 'Mahasiswa :nim dinyatakan bukan orang yang sama; biodatanya akan dikirim sebagai mahasiswa baru.',
        ],
        'validasi' => [
            'belum_diputuskan' => 'PDDIKTI mungkin sudah memegang mahasiswa ini (temuan adopsi ambigu). Jalankan adopsi lalu tautkan '
                .'atau pisahkan di layar Adopsi Mahasiswa sebelum mengirim biodata, supaya tidak terdaftar dua kali.',
        ],
    ],

];
