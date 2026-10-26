<?php

declare(strict_types=1);

/*
| Tahap dokumen proposal tugas akhir (#38). Wajib hanya pada prodi yang
| menyalakannya, dan hanya untuk tugas akhir yang diajukan sesudahnya — ADR-029.
*/

return [
    'judul' => 'Proposal',
    'prodi_wajib' => 'Wajib proposal tugas akhir',
    'prodi_wajib_hint' => 'Judul baru disetujui setelah dokumen proposal diterima',

    'status' => [
        'menunggu' => 'Menunggu Telaah',
        'revisi' => 'Perlu Revisi',
        'diterima' => 'Diterima',
        'ditolak' => 'Ditolak',
    ],

    'keputusan' => [
        'revisi' => 'Minta revisi',
        'diterima' => 'Terima',
        'ditolak' => 'Tolak',
    ],

    'field' => [
        'berkas' => 'Dokumen proposal',
        'judul' => 'Judul (bila berubah)',
        'ringkasan' => 'Ringkasan perubahan',
        'calon_pembimbing_dosen_id' => 'Calon pembimbing',
        'keputusan' => 'Keputusan',
        'catatan' => 'Catatan telaah',
    ],

    'kartu' => [
        'proposal' => 'Dokumen proposal',
        'riwayat' => 'Riwayat versi & telaah',
        'unggah' => 'Unggah proposal',
        'unggah_revisi' => 'Unggah revisi',
        'telaah' => 'Telaah versi terbaru',
        'menunggu_telaah' => 'Proposal menunggu telaah Anda',
    ],

    'keterangan' => [
        'tahap_aktif' => 'Program studi Anda mewajibkan dokumen proposal. Judul baru dapat disetujui setelah proposal diterima calon pembimbing atau koordinator program studi.',
        'belum_ada' => 'Belum ada proposal yang diunggah.',
        'versi' => 'Versi :versi',
        'diunggah' => 'Diunggah :tanggal',
        'oleh' => 'oleh :nama',
        'calon' => 'Calon pembimbing: :nama',
        'menunggu' => 'Versi terbaru sedang ditelaah.',
        'diterima' => 'Proposal diterima. Judul menunggu persetujuan program studi.',
        'judul_tertahan' => 'Judul belum dapat disetujui: proposal belum diterima.',
        'tanpa_antrean' => 'Tidak ada proposal yang menunggu telaah Anda.',
        'unduh' => 'Unduh',
    ],

    'aksi' => [
        'unggah' => 'Unggah Proposal',
        'simpan_telaah' => 'Simpan Telaah',
        'buka' => 'Buka',
    ],

    'sukses' => [
        'unggah' => 'Proposal versi :versi diunggah dan menunggu telaah.',
        'telaah' => 'Telaah disimpan: :keputusan.',
    ],

    'galat' => [
        'tahap_tidak_aktif' => 'Tahap proposal tidak berlaku untuk tugas akhir ini.',
        'status_ta' => 'Proposal hanya dapat diunggah atau ditelaah selama judul masih diajukan. Status saat ini: :status.',
        'tidak_dapat_unggah' => [
            'menunggu' => 'Versi terakhir masih ditelaah. Tunggu keputusannya sebelum mengunggah versi baru.',
            'diterima' => 'Proposal sudah diterima; tidak perlu mengunggah versi baru.',
            'ditolak' => 'Proposal ditolak. Ajukan judul baru.',
        ],
        'calon_nonaktif' => 'Calon pembimbing harus dosen aktif.',
        'sudah_diputus' => 'Versi ini sudah diputus (:status). Keputusan tidak dapat diubah.',
        'bukan_calon' => 'Hanya dosen yang disebut sebagai calon pembimbing yang dapat menelaah proposal ini.',
        'catatan_wajib' => 'Permintaan revisi dan penolakan wajib disertai catatan yang dapat dibaca mahasiswa.',
        'judul_tanpa_proposal' => 'Program studi mewajibkan dokumen proposal. Judul baru dapat disetujui setelah proposalnya diterima.',
    ],

    'log' => [
        'unggah' => 'Proposal versi :versi diunggah.',
        'telaah' => 'Proposal versi :versi ditelaah: :keputusan oleh :penelaah.',
    ],

    'notifikasi' => [
        'diajukan_judul' => 'Proposal tugas akhir menunggu telaah Anda',
        'diajukan_ringkasan' => 'Versi :versi — ":judul". Anda disebut sebagai calon pembimbing.',
        'diajukan_ajakan' => 'Telaah Proposal',
        'ditelaah_judul' => [
            'revisi' => 'Proposal tugas akhir perlu revisi',
            'diterima' => 'Proposal tugas akhir diterima',
            'ditolak' => 'Proposal tugas akhir ditolak',
        ],
        'ditelaah_ringkasan' => 'Versi :versi: :keputusan. Catatan: :catatan',
        'ditelaah_ajakan' => 'Buka Tugas Akhir',
    ],
];
