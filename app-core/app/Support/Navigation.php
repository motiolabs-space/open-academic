<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;

/**
 * The sidebar tree for each portal.
 *
 * Kept out of Blade so the three navigations can be compared side by side, and
 * so a route rename breaks in one place rather than in three templates. Items
 * whose route does not exist yet are marked disabled instead of being hidden —
 * an operator can see what the module map promises and what is still coming.
 */
final class Navigation
{
    /**
     * @return array<int, array{title: ?string, items: array<int, array{label: string, icon: string, route: ?string, badge: ?int}>}>
     */
    public static function for(UserRole $role): array
    {
        return match ($role) {
            UserRole::Mahasiswa => self::mahasiswa(),
            UserRole::Dosen => self::dosen(),
            UserRole::Staff => self::staff(),
        };
    }

    /** @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}> */
    private static function mahasiswa(): array
    {
        return [
            [
                'title' => null,
                'items' => [
                    self::item('Dasbor', '▦', 'mahasiswa.dashboard'),
                    self::item('Rencana Studi (KRS)', '▤', 'mahasiswa.krs'),
                    self::item('Jadwal Kuliah', '◫', 'mahasiswa.jadwal'),
                    self::item('Presensi Mandiri', '⛶', 'mahasiswa.presensi'),
                    self::item('KHS & Transkrip', '≡', 'mahasiswa.khs'),
                    self::item('Capaian Pembelajaran', '◔', 'mahasiswa.capaian'),
                    self::item('Tugas Akhir', '✍', 'mahasiswa.tugas-akhir'),
                    self::item('Surat & Dokumen', '✉', 'mahasiswa.surat'),
                    self::item('Evaluasi Dosen', '☆', 'mahasiswa.edom'),
                    self::item('Tagihan & Pembayaran', '◈', 'mahasiswa.tagihan'),
                    self::item('Profil Akademik', '○', 'mahasiswa.profil'),
                    self::item('Aplikasi Terhubung', '⌘', 'sso.aplikasi'),
                ],
            ],
        ];
    }

    /** @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}> */
    private static function dosen(): array
    {
        return [
            [
                'title' => 'PENGAJARAN',
                'items' => [
                    self::item('Dasbor', '▦', 'dosen.dashboard'),
                    self::item('Kelas Diampu', '▤', 'dosen.kelas'),
                    self::item('Input Nilai', '≡', 'dosen.nilai'),
                    self::item('Presensi', '◫', 'dosen.presensi'),
                    self::item('RPS & Jurnal', '❏', 'dosen.rps'),
                    self::item('Analitik Kelas', '◔', 'dosen.analitik'),
                    self::item('Hasil EDOM', '☆', 'dosen.edom'),
                ],
            ],
            [
                'title' => 'PERWALIAN',
                'items' => [
                    self::item('Persetujuan KRS', '✓', 'dosen.persetujuan-krs'),
                    self::item('Mahasiswa Bimbingan', '○', 'dosen.bimbingan'),
                    self::item('Tugas Akhir', '✍', 'dosen.tugas-akhir'),
                    self::item('Aplikasi Terhubung', '⌘', 'sso.aplikasi'),
                ],
            ],
            [
                'title' => 'KEPEGAWAIAN',
                'items' => [
                    self::item('Beban Kerja (BKD)', '⊞', 'dosen.bkd'),
                    self::item('Penilaian BKD', '✓', 'dosen.bkd.penilaian'),
                    self::item('Portofolio', '◈', 'dosen.portofolio'),
                ],
            ],
        ];
    }

    /** @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}> */
    private static function staff(): array
    {
        return [
            [
                'title' => null,
                'items' => [
                    self::item('Dasbor', '▦', 'admin.dashboard'),
                ],
            ],

            /*
             * Dikelompokkan menurut **pekerjaan**, bukan menurut entitas data.
             *
             * Orang datang ke sidebar sambil berpikir "saya harus membuka kelas
             * untuk semester depan", bukan "saya ingin sebuah program studi".
             * Kelompok per entitas memaksa mereka tahu skema basis data lebih
             * dulu untuk menemukan layarnya.
             *
             * Sebelumnya ada satu grup "KEMAHASISWAAN" berisi 18 item, dan lebih
             * dari separuhnya bukan kemahasiswaan — kepegawaian dosen, akun staf,
             * BKD, EDOM, sampai penutupan semester. Judul yang keliru lebih buruk
             * daripada tanpa judul: pembacanya berhenti mempercayai judul dan
             * mulai memindai ke-31 item satu per satu.
             */
            [
                // Urut mengikuti siklus semester: siapkan, buka, jalankan, tutup.
                'title' => 'AKADEMIK',
                'items' => [
                    self::item('Master Akademik', '▤', 'admin.master.index'),
                    self::item('Jadwal & Kelas', '◫', 'admin.kelas'),
                    self::item('Padanan & Paket', '⇄', 'admin.kurikulum-lanjutan'),
                    self::item('Koreksi Nilai', '✎', 'admin.koreksi-nilai'),
                    self::item('Penutupan Semester', '⊟', 'admin.tutup-semester'),
                ],
            ],
            [
                /*
                 * Layar harian didahulukan; sisanya mengikuti perjalanan
                 * mahasiswa — masuk, menempuh, dievaluasi, lulus.
                 *
                 * Evaluasi Studi berada di sini, bukan di AKADEMIK, karena yang
                 * dihasilkannya adalah temuan tentang seorang mahasiswa. Angka
                 * yang dibacanya memang dibekukan Penutupan Semester, dan itu
                 * ada di grup sebelumnya.
                 */
                'title' => 'MAHASISWA',
                'items' => [
                    self::item('Data Mahasiswa', '○', 'admin.mahasiswa'),
                    self::item('PMB', '◇', 'admin.pmb'),
                    self::item('Cuti Mahasiswa', '◐', 'admin.cuti'),
                    self::item('Konversi Kredit', '⇄', 'admin.konversi'),
                    self::item('Evaluasi Studi', '⚖', 'admin.evaluasi-studi'),
                    self::item('Poin Kemahasiswaan', '★', 'admin.poin-kemahasiswaan'),
                    self::item('Tugas Akhir', '✍', 'admin.tugas-akhir'),
                    self::item('Yudisium', '✦', 'admin.yudisium'),
                    self::item('Wisuda', '✧', 'admin.wisuda'),
                    self::item('Surat & Dokumen', '✉', 'admin.surat'),
                ],
            ],
            [
                // Orang yang dipekerjakan kampus, dan bagaimana kinerjanya
                // dicatat. Sebelumnya lima item ini berada di bawah judul
                // "KEMAHASISWAAN", yang jelas bukan tempatnya.
                'title' => 'SDM',
                'items' => [
                    self::item('Kepegawaian Dosen', '◎', 'admin.dosen'),
                    self::item('Akun Staf', '◉', 'admin.staff'),
                    self::item('Unit Kerja', '⌗', 'admin.unit-kerja'),
                    self::item('Beban Kerja Dosen', '⊞', 'admin.bkd.index'),
                    self::item('Evaluasi Dosen', '☆', 'admin.edom.index'),
                ],
            ],
            [
                'title' => 'KEUANGAN',
                'items' => [
                    self::item('Matriks Tarif', '⊞', 'admin.tarif'),
                    self::item('Tagihan & Rekonsiliasi', '◈', 'admin.keuangan'),
                    self::item('Beasiswa & Keringanan', '◍', 'admin.beasiswa'),

                    /*
                     * Hanya muncul bila integrasinya dinyalakan.
                     *
                     * Berbeda dari item lain di berkas ini, yang tetap terlihat
                     * meski rutenya belum ada supaya peta modulnya terbaca.
                     * Yang ini bukan modul yang belum jadi, melainkan modul yang
                     * memang tidak dipakai kampus ini — dan menu untuk sistem
                     * yang tidak mereka miliki hanya mengundang pertanyaan.
                     */
                    ...(Akuntansi::aktif()
                        ? [self::item('Integrasi Akuntansi', '⇄', 'admin.akuntansi.index')]
                        : []),
                ],
            ],
            [
                /*
                 * Apa yang dikirim kampus ke luar, dan apa yang dapat dibaca
                 * sistem lain.
                 *
                 * Verifikasi Data IKU ada di sini, bukan di MAHASISWA: isinya
                 * memang tentang mahasiswa, tapi pekerjaannya menyiapkan angka
                 * untuk dilaporkan. Log Aktivitas ikut karena ia dibuka saat
                 * seseorang menelusuri apa yang terjadi — bukan saat mengelola.
                 */
                'title' => 'PELAPORAN',
                'items' => [
                    self::item('Neo Feeder PDDIKTI', '⇅', 'admin.feeder'),
                    self::item('Campus Bridge', '⌘', 'admin.bridge'),
                    self::item('Verifikasi Data IKU', '◈', 'admin.iku-records'),
                    self::item('Log Aktivitas', '◷', 'admin.log'),
                ],
            ],
            [
                'title' => 'SISTEM',
                'items' => [
                    /*
                     * Lapisan mutu & perencanaan di atas struktur organisasi.
                     *
                     * Yang di sini adalah rencana kinerja dan Audit Mutu
                     * Internal — keduanya bersubjek unit kerja. Dasbor IKU dan
                     * borang akreditasi tidak di sini: keduanya butuh data
                     * penelitian, PkM dan keuangan yang aplikasi ini tidak
                     * punya. Lihat docs/KINERJA.md dan docs/SPMI.md.
                     */
                    self::item('Rencana Kinerja', '◎', 'admin.kinerja'),
                    self::item('SPMI & Audit Mutu', '⚖', 'admin.spmi'),

                    self::item('Pengumuman', '✉', 'admin.pengumuman'),
                    self::item('Pengaturan', '⚙', 'admin.pengaturan'),
                ],
            ],
        ];
    }

    /** @return array{label: string, icon: string, route: ?string, badge: ?int} */
    private static function item(string $label, string $icon, string $route, ?int $badge = null): array
    {
        return [
            'label' => $label,
            'icon' => $icon,

            // Routes for modules that are not built yet resolve to null, which
            // the sidebar renders as a disabled item.
            'route' => Route::has($route) ? $route : null,

            'badge' => $badge,
        ];
    }
}
