<?php

declare(strict_types=1);

/*
 * Layanan swalayan mahasiswa (ADR-040): ajuan cuti, ajuan beasiswa, lapor
 * prestasi, dan status konversi kredit. Mahasiswa mengajukan; staf memutuskan.
 */
return [

    'diajukan_pada' => 'Diajukan :tanggal',
    'unduh_berkas' => 'Unduh berkas',
    'berkas_petunjuk' => 'PDF atau gambar (JPG/PNG), maksimal :mb MB. Disimpan di penyimpanan privat.',

    'kolom' => [
        'semester' => 'Semester',
        'jenis' => 'Jenis',
        'jenis_alasan' => 'Jenis & Alasan',
        'alasan' => 'Alasan',
        'status' => 'Status',
    ],

    'galat' => [
        'berkas_jenis' => 'Berkas harus PDF atau gambar (JPG/PNG).',
    ],

    'cuti' => [
        'judul' => 'Cuti Akademik',
        'riwayat' => 'Riwayat Pengajuan Cuti',
        'ajukan' => 'Ajukan Cuti',
        'dokumen' => 'Dokumen Pendukung',
        'tombol' => 'Kirim Pengajuan',
        'sukses' => 'Pengajuan cuti untuk :semester terkirim dan menunggu keputusan BAAK.',
        'kosong' => 'Belum ada pengajuan cuti',
        'kosong_penjelasan' => 'Pengajuan yang Anda kirim akan tampil di sini beserta keputusannya.',
        'bukan_aktif' => 'Cuti hanya dapat diajukan oleh mahasiswa berstatus Aktif. Status Anda saat ini: :status.',
        'masih_menunggu' => 'Masih ada pengajuan cuti yang menunggu keputusan BAAK. Pengajuan berikutnya dapat dikirim setelah pengajuan itu diputus.',
        'tidak_ada_semester' => 'Belum ada semester yang dapat diajukan cuti. Semester berikutnya muncul di sini setelah dibuat oleh bagian akademik.',
        'aturan' => 'Cuti dapat diajukan untuk semester berjalan atau semester reguler berikutnya, satu kali per semester, dan tidak dapat diajukan bila Anda masih memegang KRS yang diajukan atau disetujui pada semester itu. Status Anda baru berubah menjadi Cuti setelah BAAK menyetujui.',
        'belum_berjalan' => 'Semester ini belum aktif. Menyetujui sekarang langsung mengubah status mahasiswa menjadi Cuti.',

        'jenis' => [
            'akademik' => 'Cuti Akademik',
            'sakit' => 'Sakit',
            'lainnya' => 'Lainnya',
        ],

        'galat' => [
            'semester_tidak_dibuka' => 'Cuti hanya dapat diajukan dari portal untuk semester berjalan atau semester reguler berikutnya.',
            'alasan_wajib' => 'Tuliskan alasan pengajuan cuti.',
            'alasan_singkat' => 'Alasan cuti terlalu singkat; jelaskan sedikitnya dalam satu kalimat.',
        ],
    ],

    'beasiswa' => [
        'judul' => 'Beasiswa & Keringanan',
        'konteks' => ':jumlah periode pendaftaran dibuka',
        'terbuka' => 'Pendaftaran Dibuka',
        'tidak_ada_terbuka' => 'Belum ada periode pendaftaran yang dibuka',
        'tidak_ada_terbuka_penjelasan' => 'Periode pendaftaran beasiswa dan keringanan diumumkan oleh Bagian Keuangan dan muncul di sini selama masa pendaftaran.',
        'ajuan_saya' => 'Ajuan Saya',
        'ajuan_kosong' => 'Belum ada ajuan beasiswa',
        'penerimaan' => 'Beasiswa yang Saya Terima',
        'penerimaan_kosong' => 'Anda belum tercatat sebagai penerima beasiswa.',
        'dibuka_sampai' => 'Dibuka sampai :tanggal',
        'mulai_semester' => 'Berlaku mulai :semester',
        'ipk_minimum' => 'IPK minimum :ipk',
        'syarat' => 'Persyaratan',
        'berkas_diminta' => 'Berkas yang diminta',
        'motivasi' => 'Alasan mengajukan',
        'motivasi_petunjuk' => 'Jelaskan keadaan Anda dan mengapa Anda layak menerima skema ini.',
        'tombol' => 'Kirim Ajuan',
        'tidak_dapat' => 'Belum dapat mengajukan: :alasan',
        'aturan' => 'Ajuan diperiksa Bagian Keuangan. Potongan tagihan baru berlaku setelah Anda ditetapkan sebagai penerima.',
        'ipk_saat_ajuan' => 'IPK saat mengajukan: :ipk',
        'periode_ajuan' => 'Periode Pendaftaran',

        'status' => [
            'diajukan' => 'Menunggu Pemeriksaan',
            'ditetapkan' => 'Ditetapkan',
            'ditolak' => 'Tidak Ditetapkan',
        ],

        'halangan' => [
            'ditutup' => 'Periode pendaftaran ini sudah ditutup.',
            'bukan_aktif' => 'Hanya mahasiswa berstatus Aktif yang dapat mengajukan; status Anda :status.',
            'sudah_mengajukan' => 'Anda sudah mengajukan pada periode ini.',
            'sudah_menerima' => 'Anda masih tercatat sebagai penerima :skema.',
            'ipk' => 'IPK minimum :minimum, IPK Anda :ipk.',
        ],

        'admin' => [
            'periode' => 'Periode Pendaftaran',
            'periode_kosong' => 'Belum ada periode pendaftaran. Buka periode agar mahasiswa dapat mengajukan dari portal.',
            'buka' => 'Buka Periode Pendaftaran',
            'skema' => 'Skema',
            'nama' => 'Nama periode',
            'semester_mulai' => 'Semester mulai berlaku',
            'dibuka_mulai' => 'Dibuka mulai',
            'dibuka_sampai' => 'Dibuka sampai',
            'syarat' => 'Persyaratan (dibaca mahasiswa)',
            'ipk_minimum' => 'IPK minimum (opsional)',
            'berkas_diminta' => 'Berkas yang diminta',
            'berkas_diminta_petunjuk' => 'Satu berkas per baris, misalnya "Surat keterangan penghasilan orang tua".',
            'tombol_buka' => 'Buka Periode',
            'tutup' => 'Tutup Pendaftaran',
            'tutup_konfirmasi' => 'Tutup pendaftaran sekarang? Ajuan yang sudah masuk tetap diperiksa.',
            'lihat' => 'Periksa ajuan',
            'status_dibuka' => 'Dibuka',
            'status_ditutup' => 'Ditutup',
            'status_belum' => 'Belum dibuka',
            'menunggu' => ':jumlah menunggu',
            'ajuan' => 'Ajuan Masuk',
            'ajuan_kosong' => 'Belum ada ajuan pada periode ini.',
            'kuota' => 'Sisa kuota skema: :sisa',
            'kuota_bebas' => 'Skema tanpa batas kuota',
            'tetapkan' => 'Tetapkan Penerima',
            'tetapkan_konfirmasi' => 'Tetapkan :nama sebagai penerima? Potongan langsung diterapkan ke tagihan semester yang dicakup.',
            'semester_selesai' => 'Berakhir pada (opsional)',
            'nomor_sk' => 'Nomor SK (opsional)',
            'catatan' => 'Catatan untuk mahasiswa',
            'tolak' => 'Tolak',
            'alasan_tolak' => 'Alasan penolakan',
            'penerima_tercatat' => 'Penerima tercatat',
        ],

        'sukses' => [
            'periode_dibuka' => 'Periode ":nama" dibuka; mahasiswa dapat mengajukan dari portal.',
            'periode_ditutup' => 'Pendaftaran ditutup. Ajuan yang sudah masuk tetap dapat diputus.',
            'diajukan' => 'Ajuan Anda untuk ":nama" terkirim dan menunggu pemeriksaan Bagian Keuangan.',
            'ditetapkan' => ':nama ditetapkan sebagai penerima; potongan diterapkan ke tagihan yang dicakup.',
            'ditolak' => 'Ajuan ditolak; mahasiswa diberi tahu beserta alasannya.',
        ],

        'galat' => [
            'skema_nonaktif' => 'Skema :skema sedang nonaktif dan tidak dapat dibuka pendaftarannya.',
            'tanggal_periode' => 'Tanggal tutup pendaftaran tidak boleh mendahului tanggal buka maupun hari ini.',
            'sudah_ditutup' => 'Periode ini sudah ditutup.',
            'motivasi_wajib' => 'Tuliskan alasan Anda mengajukan.',
            'motivasi_singkat' => 'Alasan terlalu singkat; jelaskan keadaan Anda sedikitnya dalam dua kalimat.',
            'berkas_wajib' => 'Lampirkan setiap berkas yang diminta pada periode ini.',
            'catatan_tolak_wajib' => 'Penolakan wajib disertai alasan yang dapat dibaca mahasiswa.',
            'sudah_diputus' => 'Ajuan ini sudah diputus (:status).',
        ],

        'notifikasi' => [
            'judul_ditetapkan' => 'Anda ditetapkan sebagai penerima :skema',
            'judul_ditolak' => 'Ajuan :skema tidak ditetapkan',
            'ringkasan_ditetapkan' => 'Ajuan Anda pada :periode ditetapkan. Potongan diterapkan ke tagihan semester yang dicakup.',
            'ringkasan_ditolak' => 'Ajuan Anda pada :periode tidak ditetapkan. Catatan: :catatan',
        ],
    ],
];
