<?php

declare(strict_types=1);

return [

    'permohonan_feeder' => [
        'perihal' => 'Permohonan :jenis',
        'kepada' => 'Kepada Yth.',
        'di_tempat' => 'di tempat',
        'pembuka' => 'Dengan hormat, bersama surat ini kami mengajukan permohonan :jenis pada Pangkalan Data Pendidikan Tinggi atas mahasiswa berikut:',
        'pengantar_tabel' => 'Data yang kami mohonkan untuk diubah:',
        'kolom' => [
            'no' => 'No.',
            'data' => 'Data',
            'lama' => 'Tercatat di PDDIKTI',
            'baru' => 'Seharusnya',
            'alasan' => 'Alasan',
        ],
        'kosong_nilai' => '(kosong)',
        'lampiran_judul' => 'Sebagai bahan pertimbangan, bersama surat ini kami lampirkan:',
        'penutup' => 'Kami menyatakan bahwa data pengganti di atas sesuai dengan dokumen sumbernya dan bertanggung jawab penuh atas kebenarannya. '
            .'Atas perhatian dan bantuannya kami ucapkan terima kasih.',
        'ditolak_ws' => 'Ditolak web service Feeder: :sebab',

        'lampiran' => [
            'sptjm' => 'Surat Pernyataan Tanggung Jawab Mutlak pimpinan perguruan tinggi',
            'ktp' => 'Salinan Kartu Tanda Penduduk mahasiswa',
            'kk' => 'Salinan Kartu Keluarga',
            'akta_kelahiran' => 'Salinan akta kelahiran',
            'ijazah_sebelumnya' => 'Salinan ijazah jenjang pendidikan sebelumnya',
            'sk_nim' => 'Salinan keputusan penetapan Nomor Induk Mahasiswa',
            'bukti_registrasi' => 'Salinan bukti registrasi mahasiswa baru pada periode masuk yang benar',
            'sk_keluar' => 'Salinan keputusan pimpinan tentang status keluar mahasiswa',
            'sk_yudisium' => 'Salinan surat keputusan yudisium',
            'transkrip' => 'Salinan transkrip akademik',
            'ijazah' => 'Salinan ijazah',
        ],

        'konsol' => [
            'alasan' => 'Alasan permohonan',
            'terbitkan' => 'Terbitkan surat permohonan',
            'mencakup' => '{1} Mencakup permintaan ini|[2,*] Mencakup :jumlah permintaan mahasiswa ini',
            'unduh' => 'Unduh surat',
        ],

        'pesan' => [
            'terbit' => 'Surat permohonan :nomor terbit untuk :nim; :jumlah permintaan ditandai diajukan dengan nomor tersebut.',
        ],

        'galat' => [
            'bukan_permintaan' => 'Surat permohonan perubahan data ke PDDIKTI diterbitkan dari konsol Feeder, bukan diajukan mahasiswa.',
            'kosong' => 'Pilih sedikitnya satu permintaan perubahan untuk dimuat dalam surat.',
            'beda_mahasiswa' => 'Satu surat permohonan hanya untuk satu mahasiswa.',
            'beda_jenis' => 'Perubahan Data Mahasiswa dan Perubahan Jenis Keluar diajukan dengan surat terpisah.',
            'tidak_perlu_surat' => 'Permintaan perubahan :kolom tidak lagi menunggu surat: sudah diajukan, sudah bersurat, atau dapat dikirim lewat web service.',
        ],
    ],

];
