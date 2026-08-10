<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SPMI — Audit Mutu Internal
|--------------------------------------------------------------------------
|
| Standar mutu, siklus PPEPP, dan audit yang memeriksa kepatuhannya.
|
| **Borang akreditasi tidak di sini.** Ia membutuhkan data penelitian, PkM, dan
| keuangan yang bukan milik aplikasi ini, jadi ia tetap di Open Campus bersama
| dasbor IKU — lihat docs/KINERJA.md. Yang dibangun di sini adalah AMI, karena
| subjeknya adalah unit kerja dan temuannya kualitatif.
|
*/

return [

    /*
     * Jenis temuan, diurutkan dari yang terberat.
     *
     * `wajib_tindak_lanjut` adalah pembedanya. Observasi dan saran boleh
     * ditutup tanpa perbaikan — memaksakan tindak lanjut untuk keduanya membuat
     * auditor berhenti menuliskannya, dan justru catatan ringan itulah yang
     * paling sering berguna tahun berikutnya.
     */
    'jenis_temuan' => [
        'mayor' => [
            'label' => 'Ketidaksesuaian Mayor',
            'tone' => 'danger',
            'wajib_tindak_lanjut' => true,
            'tenggat_hari' => 30,
        ],
        'minor' => [
            'label' => 'Ketidaksesuaian Minor',
            'tone' => 'warning',
            'wajib_tindak_lanjut' => true,
            'tenggat_hari' => 90,
        ],
        'observasi' => [
            'label' => 'Observasi',
            'tone' => 'info',
            'wajib_tindak_lanjut' => false,
            'tenggat_hari' => null,
        ],
        'saran' => [
            'label' => 'Saran Perbaikan',
            'tone' => 'neutral',
            'wajib_tindak_lanjut' => false,
            'tenggat_hari' => null,
        ],
    ],

    /*
     * Tahap siklus PPEPP pada sebuah standar.
     *
     * Disimpan sebagai keadaan standar, bukan sebagai tabel terpisah: PPEPP
     * adalah putaran yang dilalui satu standar berulang kali, dan tabel per
     * tahap akan menghasilkan lima baris yang menceritakan satu hal.
     */
    'ppepp' => [
        'penetapan' => 'Penetapan',
        'pelaksanaan' => 'Pelaksanaan',
        'evaluasi' => 'Evaluasi',
        'pengendalian' => 'Pengendalian',
        'peningkatan' => 'Peningkatan',
    ],

    /*
     * Auditor tidak boleh mengaudit unitnya sendiri.
     *
     * Dapat dimatikan karena kampus kecil kadang tidak punya cukup auditor —
     * tapi bawaannya menolak, dan mematikannya adalah keputusan sadar yang
     * tercatat di config, bukan kelalaian yang tidak pernah terlihat.
     */
    'tolak_audit_unit_sendiri' => (bool) env('SPMI_TOLAK_AUDIT_SENDIRI', true),
];
