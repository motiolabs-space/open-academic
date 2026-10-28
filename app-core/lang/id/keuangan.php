<?php

declare(strict_types=1);

return [

    'komponen' => [
        'ukt' => 'UKT',
        'spp' => 'SPP',
        'praktikum' => 'Praktikum',
        'registrasi' => 'Registrasi',
        'wisuda' => 'Wisuda',
        'lainnya' => 'Lainnya',
        'per_sks' => 'Per SKS',
        'mata_kuliah' => 'Per Mata Kuliah',
    ],

    'tarif' => [
        'mata_kuliah' => 'Mata Kuliah',
        'mata_kuliah_bantuan' => 'Wajib untuk komponen Per Mata Kuliah; kosongkan untuk komponen lain.',
        'mata_kuliah_wajib' => 'Komponen Per Mata Kuliah harus menyebut mata kuliah yang ditagih.',
        'mata_kuliah_dilarang' => 'Mata kuliah hanya diisi untuk komponen Per Mata Kuliah.',
        'nominal_per_sks' => 'Untuk komponen Per SKS, nominal adalah tarif untuk satu SKS.',
        'per_sks_satuan' => 'per SKS',
        'dari_krs' => 'Komponen Per SKS dan Per Mata Kuliah tidak ikut penerbitan massal. Keduanya ditagih ke tagihan semester yang sama ketika Dosen Wali menyetujui KRS, dan disesuaikan ketika KRS yang direvisi disetujui kembali.',
        'simulasi_dari_krs' => 'Ditagih dari KRS yang disetujui — tidak termasuk total di bawah.',
        'total_tetap' => 'Total komponen tetap',
    ],

    'krs' => [
        'baris_per_sks' => 'Biaya per SKS — :mk (:sks SKS × :tarif)',
        'alasan_dikurangi' => 'Kelas dibatalkan dari KRS pada :tanggal, sebelum batas pengurangan tagihan.',
        'alasan_tidak_dikurangi' => 'Kelas dibatalkan dari KRS pada :tanggal, sesudah batas pengurangan tagihan; tagihan tidak berkurang.',
        'koreksi' => 'koreksi',

        'notifikasi_judul' => 'Tagihan Anda disesuaikan dengan KRS',
        'notifikasi_ringkasan' => 'Tagihan :nomor kini :total (:selisih) mengikuti KRS yang disetujui, jatuh tempo :tanggal.',
        'notifikasi_ajakan' => 'Lihat Tagihan',
    ],

    'denda' => [
        'nama_baris' => 'Denda keterlambatan',
        'nama_pembebasan' => 'Pembebasan denda keterlambatan',

        'aturan' => 'Pembayaran yang melewati jatuh tempo dikenai denda :tarif.',
        'tarif_nominal' => ':nominal per :periode keterlambatan',
        'tarif_persen' => ':persen% dari sisa tagihan per :periode keterlambatan',
        'tenggang' => 'Masa tenggang :hari hari sesudah jatuh tempo masih bebas denda.',
        'maksimum' => 'Denda dibatasi paling banyak :maksimum per tagihan.',

        'berjalan' => 'Denda yang telah berjalan pada tagihan ini: :denda.',
        'tidak_mengunci_krs' => 'Denda tidak memengaruhi syarat pembayaran untuk pengisian KRS; ambang dihitung dari tagihan pokok.',
        'dibebaskan' => 'Denda keterlambatan sebesar :denda dibebaskan oleh Bagian Keuangan pada :tanggal. Alasan: :alasan',

        'pengingat' => 'Perhatikan: :aturan',

        'berhasil_dibebaskan' => 'Denda :denda dibebaskan dan tagihan diperbarui.',
        'alasan_wajib' => 'Pembebasan denda wajib disertai alasan — penghapusan tanpa alasan tertulis tidak dapat dibedakan dari penyalahgunaan.',
        'alasan_singkat' => 'Alasan terlalu singkat untuk menjelaskan pembebasan.',

        'tombol_bebaskan' => 'Bebaskan Denda',
        'chip_dibebaskan' => 'denda dibebaskan',
        'termasuk' => 'termasuk denda :denda',

        'notifikasi_judul' => 'Denda keterlambatan Anda dibebaskan',
        'notifikasi_ringkasan' => 'Denda keterlambatan :denda pada tagihan :nomor dibebaskan oleh Bagian Keuangan. Alasan: :alasan Sisa yang harus dibayar :sisa.',
    ],

    'dispensasi' => [
        'judul' => 'Dispensasi Pembayaran',
        'nav' => 'Dispensasi',
        'konteks' => ':aktif berlaku · :menunggu menunggu keputusan',
        'penjelasan' => 'Dispensasi membuka pengisian KRS sebelum ambang pembayaran tercapai dan menunda hitungan denda sampai tanggal batasnya. Sesudah tanggal itu gerbang KRS kembali menghitung pembayaran, tanpa perlu tindakan apa pun.',

        'status' => [
            'diajukan' => 'Menunggu',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'dicabut' => 'Dicabut',
        ],
        'kedaluwarsa' => 'Berakhir',

        'kolom' => [
            'tagihan' => 'Tagihan',
            'mahasiswa' => 'Mahasiswa',
            'sampai' => 'Berlaku sampai',
            'alasan' => 'Alasan',
            'pemberi' => 'Diberikan oleh',
            'status' => 'Status',
            'sisa' => 'Sisa tagihan',
        ],

        'beri' => [
            'judul' => 'Beri Dispensasi',
            'tagihan' => 'Nomor tagihan',
            'tagihan_bantuan' => 'Nomor tagihan seperti tertera di daftar tagihan.',
            'sampai' => 'Berlaku sampai',
            'alasan' => 'Alasan',
            'tombol' => 'Beri Dispensasi',
            'konfirmasi' => 'Beri dispensasi pada tagihan ini? Pengisian KRS mahasiswa terbuka sampai tanggal batasnya.',
        ],
        'menunggu' => [
            'judul' => 'Permohonan Menunggu Keputusan',
            'kosong' => 'Tidak ada permohonan dispensasi yang menunggu.',
            'setujui' => 'Setujui',
            'tolak' => 'Tolak',
            'catatan' => 'Catatan keputusan',
            'konfirmasi_setujui' => 'Setujui permohonan dispensasi ini?',
            'konfirmasi_tolak' => 'Tolak permohonan dispensasi ini?',
        ],
        'berlaku' => [
            'judul' => 'Dispensasi Berlaku',
            'kosong' => 'Tidak ada dispensasi yang sedang berlaku.',
            'cabut' => 'Cabut',
            'alasan_cabut' => 'Alasan pencabutan',
            'konfirmasi_cabut' => 'Cabut dispensasi ini? Gerbang KRS mahasiswa kembali menghitung pembayaran mulai hari ini.',
        ],
        'riwayat' => [
            'judul' => 'Riwayat Terbaru',
            'kosong' => 'Belum ada riwayat dispensasi.',
        ],

        'tautan_baris' => 'Dispensasi',
        'chip_berlaku' => 'dispensasi s.d. :tanggal',

        'berhasil_diberikan' => 'Dispensasi diberikan pada tagihan :nomor sampai :tanggal.',
        'berhasil_ditolak' => 'Permohonan dispensasi ditolak.',
        'berhasil_dicabut' => 'Dispensasi pada tagihan :nomor dicabut.',
        'berhasil_diajukan' => 'Permohonan dispensasi terkirim ke Bagian Keuangan.',

        'galat' => [
            'tanggal_lampau' => 'Tanggal batas dispensasi harus sesudah hari ini.',
            'alasan_wajib' => 'Dispensasi wajib disertai alasan — penundaan kewajiban tanpa alasan tertulis tidak dapat dibedakan dari penyalahgunaan.',
            'alasan_singkat' => 'Alasan terlalu singkat untuk menjelaskan dispensasi.',
            'catatan_tolak_wajib' => 'Penolakan wajib disertai catatan untuk mahasiswa.',
            'tagihan_lunas' => 'Tagihan :nomor sudah lunas; dispensasi tidak diperlukan.',
            'tagihan_tidak_ada' => 'Tagihan dengan nomor itu tidak ditemukan.',
            'bukan_menunggu' => 'Permohonan ini sudah diputus.',
            'tidak_berlaku' => 'Hanya dispensasi yang sedang berlaku yang dapat dicabut.',
            'sudah_ada_permohonan' => 'Permohonan dispensasi untuk tagihan ini masih menunggu keputusan.',
            'sudah_berlaku' => 'Dispensasi pada tagihan ini masih berlaku sampai :tanggal.',
        ],

        'mahasiswa' => [
            'aktif' => 'Dispensasi berlaku hingga :tanggal — pengisian KRS tetap terbuka meski tagihan belum lunas, dan denda tidak dihitung sampai tanggal itu.',
            'aktif_alasan' => 'Alasan: :alasan',
            'menunggu' => 'Permohonan dispensasi Anda sampai :tanggal sedang menunggu keputusan Bagian Keuangan.',
            'ditolak' => 'Permohonan dispensasi Anda ditolak pada :tanggal. Catatan: :catatan',
            'dicabut' => 'Dispensasi Anda dicabut pada :tanggal. Alasan: :alasan',
            'ajukan_judul' => 'Ajukan Dispensasi',
            'ajukan_penjelasan' => 'Bila belum dapat memenuhi ambang pembayaran sebelum batas KRS, ajukan dispensasi beserta alasannya. Bagian Keuangan memutuskan dan Anda akan menerima pemberitahuan.',
            'ajukan_sampai' => 'Sampai tanggal',
            'ajukan_alasan' => 'Alasan',
            'ajukan_tombol' => 'Ajukan Dispensasi',
        ],

        'notifikasi' => [
            'judul_diberikan' => 'Dispensasi pembayaran diberikan',
            'ringkasan_diberikan' => 'Tagihan :nomor mendapat dispensasi sampai :tanggal. Pengisian KRS terbuka meski pembayaran belum mencapai ambang.',
            'judul_ditolak' => 'Permohonan dispensasi ditolak',
            'ringkasan_ditolak' => 'Permohonan dispensasi untuk tagihan :nomor ditolak. Catatan: :catatan',
            'judul_dicabut' => 'Dispensasi pembayaran dicabut',
            'ringkasan_dicabut' => 'Dispensasi pada tagihan :nomor dicabut. Alasan: :alasan',
            'ajakan' => 'Lihat Tagihan',
        ],

        'digantikan' => 'Digantikan dispensasi baru sampai :tanggal.',
    ],

];
