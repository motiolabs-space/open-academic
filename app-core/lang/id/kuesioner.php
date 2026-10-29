<?php

declare(strict_types=1);

return [

    'judul' => 'Kuesioner',
    'konteks' => ':terbit kuesioner sedang dibuka',

    'status' => [
        'draf' => 'Draf',
        'terbit' => 'Dibuka',
        'ditutup' => 'Ditutup',
    ],

    'daftar' => [
        'judul' => 'Daftar Kuesioner',
        'kode' => 'Kode',
        'nama' => 'Nama',
        'sasaran' => 'Sasaran',
        'periode' => 'Periode',
        'responden' => 'Responden',
        'status' => 'Status',
        'kosong' => 'Belum ada kuesioner',
        'kosong_deskripsi' => 'Buat kuesioner pertama dengan formulir di samping. Kuesioner tersimpan sebagai draf sampai diterbitkan.',
        'semua_prodi' => 'Semua prodi',
        'tanpa_batas' => 'Tanpa batas waktu',
        'anonim' => 'Anonim',
        'beridentitas' => 'Beridentitas',
    ],

    'form' => [
        'buat_judul' => 'Buat Kuesioner',
        'ubah_judul' => 'Data Kuesioner',
        'kode' => 'Kode',
        'kode_hint' => 'Huruf, angka, titik, garis bawah, atau tanda hubung. Contoh: KEPUASAN-2026.',
        'nama' => 'Nama kuesioner',
        'deskripsi' => 'Pengantar untuk responden',
        'sasaran' => 'Diisi oleh',
        'prodi' => 'Program studi',
        'prodi_hint' => 'Kosongkan untuk seluruh kampus. Tidak berlaku bagi staf.',
        'anonim' => 'Anonim',
        'anonim_hint' => 'Jawaban disimpan tanpa identitas',
        'anonim_peringatan' => 'Pilihan anonim tidak dapat diubah setelah kuesioner dibuat: ia menentukan tabel tempat jawaban disimpan, dan jawaban yang diberikan tanpa nama tidak boleh belakangan diberi nama.',
        'anonim_tetap' => 'Bentuk kuesioner ini tetap :bentuk sejak dibuat.',
        'ambang' => 'Ambang responden',
        'ambang_hint' => 'Hasil kuesioner anonim tertahan sampai sekian orang mengisi. Bawaan: :ambang.',
        'mulai' => 'Dibuka mulai',
        'selesai' => 'Ditutup sesudah',
        'simpan' => 'Simpan',
        'buat' => 'Buat sebagai draf',
        'terkunci' => 'Sasaran dan ambang terkunci sejak kuesioner pertama kali diterbitkan.',
    ],

    'pertanyaan' => [
        'judul' => 'Pertanyaan',
        'tambah' => 'Tambah Pertanyaan',
        'teks' => 'Pertanyaan',
        'tipe' => 'Jenis jawaban',
        'opsi' => 'Pilihan jawaban',
        'opsi_hint' => 'Satu pilihan per baris; sekurang-kurangnya dua. Hanya untuk jenis Pilihan.',
        'wajib' => 'Wajib dijawab',
        'opsional' => 'Opsional',
        'hapus' => 'Hapus',
        'hapus_konfirmasi' => 'Hapus pertanyaan ini?',
        'kosong' => 'Belum ada pertanyaan. Kuesioner tanpa pertanyaan tidak dapat diterbitkan.',
        'terkunci' => 'Pertanyaan terkunci sejak kuesioner pertama kali diterbitkan, agar setiap jawaban tetap menjawab pertanyaan yang sama.',
    ],

    'aksi' => [
        'terbitkan' => 'Terbitkan',
        'terbitkan_konfirmasi' => 'Terbitkan kuesioner ini? Sesudah terbit, pertanyaan tidak dapat diubah lagi.',
        'buka_kembali' => 'Buka kembali',
        'tutup' => 'Tutup',
        'tutup_konfirmasi' => 'Tutup kuesioner ini? Responden tidak lagi dapat mengisinya.',
        'hasil' => 'Lihat hasil',
        'ekspor' => 'Ekspor CSV',
    ],

    'ringkasan' => [
        'responden' => 'Responden',
        'pembuat' => 'Dibuat oleh',
        'diterbitkan' => 'Pertama terbit',
        'bentuk' => 'Bentuk',
    ],

    'hasil' => [
        'judul' => 'Hasil Kuesioner',
        'rerata' => 'Rerata',
        'jawaban' => ':jumlah jawaban',
        'tertahan_judul' => 'Hasil masih tertahan',
        'tertahan' => 'Kuesioner anonim ini baru diisi :responden dari sekurang-kurangnya :ambang responden. Hasilnya dibuka setelah ambang tercapai, agar tidak seorang pun dapat dikenali dari jawabannya.',
        'anonim_catatan' => 'Kuesioner anonim: jawaban tidak memuat identitas, dan isian bebas diurutkan menurut abjad, bukan menurut urutan pengisian.',
        'teks_lebih' => ':sisa jawaban lain ada di berkas ekspor.',
        'teks_kosong' => 'Belum ada isian.',
        'kembali' => 'Kembali ke kuesioner',
    ],

    'ekspor' => [
        'kuesioner' => 'Kuesioner',
        'responden' => 'Responden',
        'bentuk' => 'Bentuk',
        'anonim' => 'Anonim — jawaban tidak dapat ditautkan ke responden maupun satu sama lain',
        'beridentitas' => 'Beridentitas',
        'nama' => 'Nama',
        'nomor_induk' => 'Nomor induk / surel',
        'diisi' => 'Diisi pada',
    ],

    'isi' => [
        'nav' => 'Kuesioner',
        'judul' => 'Kuesioner untuk Anda',
        'konteks' => 'Kuesioner yang sedang dibuka bagi Anda',
        'konteks_form' => ':jumlah pertanyaan',
        'kosong' => 'Tidak ada kuesioner untuk Anda saat ini',
        'kosong_deskripsi' => 'Kuesioner yang ditujukan kepada Anda akan tampil di sini selama masa pengisiannya.',
        'sudah' => 'Sudah diisi',
        'belum' => 'Belum diisi',
        'isi' => 'Isi sekarang',
        'sampai' => 'Sampai :tanggal',
        'anonim' => 'Jawaban Anda tersimpan tanpa identitas. Kampus hanya mencatat bahwa Anda sudah mengisi, tidak pernah apa yang Anda jawab.',
        'beridentitas' => 'Kuesioner ini tidak anonim: jawaban Anda tersimpan bersama nama Anda.',
        'sudah_isi' => 'Anda sudah mengisi kuesioner ini. Terima kasih.',
        'tidak_dibuka' => 'Kuesioner ini sedang tidak dibuka untuk diisi.',
        'kirim' => 'Kirim jawaban',
        'kirim_konfirmasi' => 'Kirim jawaban? Jawaban tidak dapat diubah setelah dikirim.',
        'teks_placeholder' => 'Tulis jawaban Anda',
        'pilih' => 'Pilih salah satu',
        'skala_rendah' => 'Sangat kurang',
        'skala_tinggi' => 'Sangat baik',
        'terkirim' => 'Jawaban Anda tersimpan. Terima kasih.',
        'terkirim_anonim' => 'Jawaban Anda tersimpan tanpa identitas. Terima kasih.',
    ],

    'pesan' => [
        'dibuat' => 'Kuesioner tersimpan sebagai draf. Tambahkan pertanyaan, lalu terbitkan.',
        'diperbarui' => 'Kuesioner diperbarui.',
        'pertanyaan_ditambah' => 'Pertanyaan ditambahkan.',
        'pertanyaan_dihapus' => 'Pertanyaan dihapus.',
        'diterbitkan' => 'Kuesioner dibuka untuk responden.',
        'ditutup' => 'Kuesioner ditutup.',
    ],

    'galat' => [
        'teks_kosong' => 'Teks pertanyaan tidak boleh kosong.',
        'opsi_kurang' => 'Pertanyaan pilihan membutuhkan sekurang-kurangnya dua pilihan jawaban.',
        'tanpa_pertanyaan' => 'Kuesioner tanpa pertanyaan tidak dapat diterbitkan.',
        'periode_lewat' => 'Periode pengisian sudah lewat. Perpanjang tanggal tutup lebih dulu.',
        'terkunci' => 'Pertanyaan tidak dapat diubah: kuesioner ini sudah pernah diterbitkan.',
        'bukan_sasaran' => 'Kuesioner ini tidak ditujukan kepada Anda.',
        'jawaban_tidak_sah' => 'Jawaban untuk pertanyaan nomor :nomor tidak sah.',
        'hasil_tertahan' => 'Hasil belum dapat diekspor: kuesioner anonim ini belum mencapai ambang :ambang responden.',
        'staf_tanpa_prodi' => 'Kuesioner untuk staf tidak dapat dibatasi per program studi.',
        'prodi_tidak_dikenal' => 'Program studi tidak ditemukan.',
    ],

    'validasi' => [
        'kode' => 'Kode hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
        'kode_dipakai' => 'Kode ini sudah dipakai kuesioner lain.',
        'periode' => 'Tanggal tutup tidak boleh sebelum tanggal buka.',
    ],

];
