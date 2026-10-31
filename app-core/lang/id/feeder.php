<?php

declare(strict_types=1);

return [

    // Pre-flight rules added for Neo Feeder 3.0.1 / 3.1 (ADR-036 §3).
    'validasi' => [
        'telepon_kosong' => 'Nomor HP mahasiswa kosong. Neo Feeder sejak versi 3.0.1 mewajibkan nomor HP pada biodata mahasiswa.',
        'telepon_tidak_valid' => 'Nomor HP ":telepon" tidak berbentuk nomor telepon (9–15 digit). Periksa sebelum dikirim.',
        'telepon_ganda' => 'Nomor HP ini juga tercatat pada mahasiswa lain. Neo Feeder versi terbaru dilaporkan menolak nomor HP yang sama untuk dua mahasiswa.',
        'email_tidak_valid' => 'Surel ":email" tidak berbentuk alamat surel; Neo Feeder versi terbaru dilaporkan mewajibkan surel yang sah.',
        'tanggal_ijazah_kosong' => 'Tanggal ijazah belum ada (:sumber), jadi Lulus/DO dikirim tanpa tanggal ijazah. Lengkapi lalu sinkronkan ulang.',
        'sumber_tanggal_ijazah' => [
            'lulus' => 'tanggal lulus pada yudisium',
            'yudisium' => 'tanggal SK yudisium',
            'wisuda' => 'lulusan belum terdaftar pada periode wisuda',
        ],
        'akm_kosong' => 'Mahasiswa tidak punya satu pun catatan aktivitas kuliah (AKM). Neo Feeder menolak meluluskan atau mengeluarkan mahasiswa tanpa riwayat AKM.',
    ],

    'flagship' => [
        'kolom' => 'Flagship',
        'bukan' => 'Bukan flagship',
        'simpan' => 'Simpan',
        'terklaim' => 'Terklaim di PDDIKTI',
        'belum_ditarik' => 'Tarik referensi Feeder untuk memilih program flagship.',
        'pesan' => [
            'ditandai' => 'Aktivitas :nama ditandai sebagai program flagship; klaimnya dikirim lewat entitas Klaim Program Flagship MBKM.',
            'dicabut' => 'Penanda program flagship untuk aktivitas :nama dicabut.',
        ],
        'galat' => [
            'tidak_dapat_dicabut' => 'Klaim program flagship aktivitas ini sudah diterima PDDIKTI, jadi penandanya tidak dapat dicabut dari sini. '
                .'Pindahkan ke program lain bila keliru, atau cabut klaimnya di Feeder.',
            'program_tidak_dikenal' => 'Program flagship ":kode" tidak ada pada referensi Feeder. Tarik ulang referensi lalu pilih kembali.',
        ],
        'validasi' => [
            'aktivitas_belum_sinkron' => 'Aktivitas MBKM belum terkirim ke Feeder (entitas aktivitas_mbkm).',
            'anggota_belum_sinkron' => 'Keanggotaan aktivitas MBKM belum terkirim ke Feeder (entitas anggota_mbkm).',
            'program_tidak_dikenal' => 'Program flagship ":kode" tidak ada pada referensi Feeder terakhir. Tarik ulang referensi (:act) lalu pilih kembali programnya.',
        ],
    ],

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

    'lampau' => [
        'judul' => 'Klaim Mahasiswa Lampau',
        'konteks' => 'Mahasiswa lama yang tercatat di PDDIKTI tetapi belum terklaim Feeder kampus',
        'tautan_konsol' => 'Mahasiswa Lampau',
        'tautan_adopsi' => 'Tidak menemukan mahasiswa lama? Klaim dulu dari daftar mahasiswa lampau.',
        'meta' => ':jumlah baris',
        'penjelasan' => 'Mahasiswa yang dilaporkan bertahun-tahun lalu dapat tercatat di PDDIKTI tanpa muncul di Feeder kampus, '
            .'sehingga adopsi tidak menemukannya. Baca daftar mahasiswa lampau per prodi dan periode masuk, pilih yang akan '
            .'diklaim, lalu klaim. Klaim hanya membawa catatannya ke Feeder kampus; penautan ke mahasiswa di sini tetap '
            .'dilakukan adopsi, yang langsung dijalankan sesudah klaim.',
        'privasi' => 'NIK hanya dibandingkan, tidak disimpan dan tidak ditampilkan.',
        'form' => [
            'prodi' => 'Program studi',
            'periode' => 'Periode masuk',
            'periode_hint' => 'Kode PDDIKTI, mis. 20191 untuk 2019/2020 Ganjil.',
            'tampilkan' => 'Tampilkan',
            'baca' => 'Baca daftar dari Feeder',
            'catatan' => 'Alasan klaim',
            'catatan_hint' => 'Wajib bila ada baris ambigu yang dipilih; dicatat pada setiap baris yang diklaim.',
            'catatan_placeholder' => 'Mis. dicocokkan dengan ijazah dan KTP pada arsip BAAK',
            'klaim' => 'Klaim yang dipilih',
            'pilih' => 'Pilih :nim untuk diklaim',
        ],
        'ringkasan' => [
            'cocok' => 'Cocok',
            'ambigu' => 'Ambigu',
            'tanpa_lokal' => 'Tidak ada di SIAKAD',
            'diklaim' => 'Sudah diklaim',
        ],
        'status' => [
            'cocok' => 'Cocok',
            'ambigu' => 'Ambigu',
            'tanpa_lokal' => 'Tidak ada di SIAKAD',
            'diklaim' => 'Diklaim',
        ],
        'masalah' => [
            'tanpa_mahasiswa_lokal' => 'Tidak ada mahasiswa di SIAKAD untuk catatan ini; klaim tidak akan dipakai siapa pun.',
            'ambigu_tanpa_alasan' => 'Pencocokan ambigu; isi alasan klaim bila catatan ini memang milik mahasiswa tersebut.',
            'kunci_tidak_lengkap' => 'Baris dari Feeder tidak memuat kunci klaim yang diperlukan.',
            'tidak_terdaftar_lagi' => 'Tidak lagi ada di daftar mahasiswa lampau PDDIKTI; baca ulang daftarnya.',
            'registrasi_dipakai' => 'Registrasi ini sudah dipegang mahasiswa lain di SIAKAD.',
        ],
        'kandidat' => [
            'nim' => 'NIM :nim',
            'lahir' => 'lahir :tanggal',
            'nik_cocok' => 'NIK sama',
            'nik_beda' => 'NIK berbeda',
            'nik_tidak_ada' => 'NIK tidak dapat dibandingkan',
        ],
        'percobaan' => 'Ditolak Feeder (percobaan ke-:ke): :pesan',
        'diklaim_oleh' => 'Diklaim :nama pada :tanggal',
        'diklaim_catatan' => 'Alasan: :catatan',
        'tertaut' => 'Sudah tertaut di SIAKAD',
        'belum_tertaut' => 'Belum tertaut — lihat layar Adopsi Mahasiswa',
        'galat_judul' => 'Klaim tidak dikirim. Perbaiki atau batalkan pilihan baris berikut:',
        'ditolak_judul' => 'Ditolak Feeder (dapat dicoba lagi):',
        'kosong' => [
            'judul' => 'Belum ada daftar mahasiswa lampau',
            'deskripsi' => 'Pilih program studi dan periode masuk, lalu baca daftarnya dari Feeder.',
        ],
        'pesan' => [
            'dibaca' => ':prodi :periode: :feeder mahasiswa lampau di Feeder — :cocok cocok, :ambigu ambigu, :tanpa tidak ada di SIAKAD.',
            'tidak_dikirim' => 'Klaim tidak dikirim: :jumlah baris tidak dapat diklaim.',
            'diklaim' => ':diklaim diklaim, :ditolak ditolak Feeder, :dilewati sudah diklaim sebelumnya.',
            'diadopsi' => 'Adopsi dijalankan ulang: :jumlah mahasiswa diadopsi.',
            'adopsi_gagal' => 'Adopsi sesudah klaim gagal (:pesan); jalankan adopsi dari layar Adopsi Mahasiswa.',
            'menunggu_keputusan' => 'Menunggu keputusan di layar Adopsi Mahasiswa: :nim.',
            'belum_teradopsi' => 'Belum ditemukan adopsi: :nim. Jalankan adopsi lagi sebelum sinkronisasi.',
        ],
        'validasi' => [
            'belum_diadopsi' => 'Mahasiswa ini sudah diklaim dari daftar mahasiswa lampau PDDIKTI tetapi belum diadopsi. '
                .'Jalankan adopsi untuk prodi dan periode masuknya sebelum mengirim biodata, supaya tidak terdaftar dua kali.',
        ],
    ],

    // Web-service dictionary check (ADR-042 §1).
    'kamus' => [
        'judul' => 'Kamus Web Service',
        'konteks' => 'Medan yang diterima Neo Feeder kampus, dibandingkan dengan medan yang dikirim SIAKAD',
        'tautan_konsol' => 'Kamus WS',
        'penjelasan' => 'Nama medan pada web service Neo Feeder hanya dapat dipastikan dari kamus yang dijawab Feeder kampus '
            .'sendiri (GetDictionary). Pemeriksaan membaca kamus setiap aksi yang dipakai SIAKAD, menyimpannya, lalu '
            .'membandingkannya dengan medan yang dikirim setiap entitas. Hasilnya laporan: tidak ada pemetaan atau '
            .'pengaturan yang diubah otomatis. Jalankan ulang setiap kali Neo Feeder kampus diperbarui.',
        'privasi' => 'Isi kamus disimpan di basis data kampus ini saja dan tidak pernah dikirim ke mana pun.',
        'form' => [
            'versi' => 'Versi Neo Feeder',
            'versi_hint' => 'Isi bila Feeder tidak melaporkan versinya sendiri, mis. 3.1.',
            'periksa' => 'Baca kamus dari Feeder',
        ],
        'ringkasan' => [
            'diperiksa' => 'Diperiksa',
            'versi' => 'Versi Feeder',
            'aksi' => 'Aksi dibaca',
            'gagal' => 'Aksi gagal dibaca',
            'selisih' => 'Entitas berselisih',
            'oleh' => 'Dijalankan oleh',
            'perintah' => 'perintah artisan',
        ],
        'versi_tidak_diketahui' => 'tidak dilaporkan',
        'cocok' => 'Cocok',
        'tidak_terbaca' => 'Tidak terbaca',
        'semua_cocok' => 'Seluruh medan yang dikirim dikenal kamus, dan tidak ada medan wajib yang tertinggal.',
        'opsional' => ':jumlah medan opsional di kamus tidak dikirim: :daftar',
        'riwayat' => 'Pemeriksaan sebelumnya',
        'kolom' => [
            'entitas' => 'Entitas',
            'aksi' => 'Aksi',
            'temuan' => 'Temuan',
            'dikirim' => 'Dikirim SIAKAD',
            'kamus' => 'Di kamus Feeder',
        ],
        'jenis' => [
            'tidak_dikenal' => 'Tidak dikenal Feeder',
            'wajib_tidak_dikirim' => 'Wajib, tidak dikirim',
            'beda_nama' => 'Beda nama',
            'aksi_tidak_terbaca' => 'Aksi tidak terbaca',
        ],
        'wajib' => 'wajib',
        'galat' => [
            'ditolak' => 'Feeder menolak permintaan kamus untuk aksi ini.',
            'bentuk' => 'Balasan kamus tidak berbentuk daftar medan yang dikenali; periksa versi Feeder atau nama parameter kamus.',
        ],
        'kosong' => [
            'judul' => 'Kamus belum pernah dibaca',
            'deskripsi' => 'Baca kamus dari Neo Feeder kampus untuk membandingkan nama medan dengan yang dikirim SIAKAD.',
        ],
        'konsol' => [
            'judul' => 'Kamus Web Service',
            'belum' => 'Kamus Feeder belum pernah dibaca. Bacalah sesudah memasang atau memperbarui Neo Feeder.',
            'ringkas' => 'Dibaca :tanggal (versi :versi): :selisih dari :entitas entitas berselisih dengan kamus.',
            'buka' => 'Buka laporan',
        ],
        'pesan' => [
            'selesai' => 'Kamus dibaca untuk :aksi aksi (:gagal gagal dibaca); :selisih entitas berselisih dengan kamus.',
        ],
        'saran' => [
            'judul' => 'Saran pengaturan nama medan',
            'sekarang' => 'Sekarang',
            'tidak_dikirim' => '(tidak dikirim)',
            'tidak_sah' => 'Nilai ini bukan nama medan yang sah dan diabaikan; HP memakai nama bawaan, tanggal ijazah tidak dikirim.',
            'catatan' => 'Saran tidak diterapkan otomatis. Ubah .env di server, jalankan php artisan config:cache, lalu baca ulang kamus. '
                .'Mengubah nama medan mengirim ulang baris yang sudah diterima Feeder sebagai pembaruan pada sinkronisasi berikutnya.',
            'medan' => [
                'FEEDER_MEDAN_HP' => 'Nomor HP pada biodata mahasiswa',
                'FEEDER_MEDAN_TANGGAL_IJAZAH' => 'Tanggal ijazah pada Lulus/DO',
            ],
            'status' => [
                'sesuai' => 'Sesuai kamus',
                'ubah' => 'Ubah',
                'isi' => 'Dapat diisi',
                'kosongkan' => 'Tidak ada di kamus',
                'tidak_ditemukan' => 'Tidak ditemukan',
                'tidak_terbaca' => 'Kamus tidak terbaca',
                'tidak_sah' => 'Tidak sah',
            ],
        ],
        'perintah' => [
            'saran' => 'Saran: :baris',
            'ringkasan' => 'Kamus dibaca untuk :aksi aksi (:gagal gagal), versi Feeder: :versi.',
            'ada_selisih' => ':jumlah entitas berselisih dengan kamus. Laporan lengkap ada di konsol Feeder → Kamus WS.',
        ],
    ],

];
