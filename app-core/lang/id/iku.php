<?php

declare(strict_types=1);

/*
 * Labels for the facts Open Academic supplies to IKU reporting (ADR-036).
 *
 * Numbering follows Kepmendiktisaintek 358/M/KEP/2025. Collaborative classes
 * and practitioner teaching were indicators under the older eight-indicator
 * numbering and have no 2026 counterpart, so they are labelled by what they
 * are rather than by a number that no longer means them.
 */
return [
    'kolaboratif' => 'Kolaboratif',
    'kelas_kolaboratif' => 'Kelas kolaboratif',
    'metode_kolaboratif' => 'Metode kolaboratif',
    'praktisi' => 'Praktisi',

    'data' => [
        'aktivitas_meta' => 'Sumber IKU 3',
        'aktivitas_kosong' => 'Kegiatan mahasiswa di luar program studi muncul di sini untuk diverifikasi sebelum menjadi bukti IKU 3.',
        'penugasan_meta' => 'Sumber luaran IKU 4–6 dan keterlibatan kebijakan IKU 8',
    ],

    'yudisium_kosong' => 'Lulusan yang ditetapkan di sini menjadi populasi awal tracer study IKU 2 di Open Campus.',
    'praktisi_instansi_hint' => 'Instansi asal praktisi dibaca Open Campus lewat Campus Bridge.',
    'praktisi_instansi_wajib' => 'Instansi asal wajib diisi untuk dosen praktisi — itulah yang membedakan praktisi dari dosen biasa di Campus Bridge.',

    // Collaborative-class rule: a class flagged case method / team-based
    // project should take at least the threshold share of its marks from
    // participatory and project work.
    'rencana' => [
        'kurang' => ':pesan Kelas ini ditandai case method / team-based project, tetapi bobot partisipatif + proyek baru :bobot% — kelas kolaboratif menuntut paling sedikit :ambang%.',
        'memenuhi' => ' Bobot partisipatif + proyek :bobot% memenuhi kriteria kelas kolaboratif.',
        'ambang_kurang' => 'kelas kolaboratif mulai',
        'ambang_memenuhi' => 'memenuhi kriteria kelas kolaboratif',
    ],
    'validator_bobot_kurang' => 'Kelas ditandai case method / team-based project, tetapi bobot partisipatif + proyek hanya :bobot% (kriteria kelas kolaboratif: :ambang%). Penandanya tetap terkirim; periksa rencana evaluasinya.',
];
