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
                'title' => 'UTAMA',
                'items' => [
                    self::item('Dasbor', '▦', 'admin.dashboard'),
                    self::item('Master Akademik', '▤', 'admin.master.index'),
                    self::item('Jadwal & Kelas', '◫', 'admin.kelas'),
                ],
            ],
            [
                'title' => 'KEMAHASISWAAN',
                'items' => [
                    self::item('Data Mahasiswa', '○', 'admin.mahasiswa'),
                    self::item('Kepegawaian Dosen', '◎', 'admin.dosen'),
                    self::item('Akun Staf', '◉', 'admin.staff'),
                    self::item('PMB', '◇', 'admin.pmb'),
                    self::item('Cuti Mahasiswa', '◐', 'admin.cuti'),
                    self::item('Tugas Akhir', '✍', 'admin.tugas-akhir'),
                    self::item('Surat & Dokumen', '✉', 'admin.surat'),
                    self::item('Konversi Kredit', '⇄', 'admin.konversi'),
                    self::item('Evaluasi Dosen', '☆', 'admin.edom.index'),
                    self::item('Beban Kerja Dosen', '⊞', 'admin.bkd.index'),
                    self::item('Yudisium', '✦', 'admin.yudisium'),
                    self::item('Wisuda', '✧', 'admin.wisuda'),
                    self::item('Verifikasi Data IKU', '◈', 'admin.iku-records'),
                    self::item('Koreksi Nilai', '✎', 'admin.koreksi-nilai'),
                    self::item('Penutupan Semester', '⊟', 'admin.tutup-semester'),
                ],
            ],
            [
                'title' => 'KEUANGAN',
                'items' => [
                    self::item('Matriks Tarif', '⊞', 'admin.tarif'),
                    self::item('Tagihan & Rekonsiliasi', '◈', 'admin.keuangan'),
                    self::item('Beasiswa & Keringanan', '◍', 'admin.beasiswa'),
                ],
            ],
            [
                'title' => 'INTEGRASI',
                'items' => [
                    self::item('Neo Feeder PDDIKTI', '⇅', 'admin.feeder'),
                    self::item('Campus Bridge', '⌘', 'admin.bridge'),
                    self::item('Pengumuman', '✉', 'admin.pengumuman'),
                    self::item('Log Aktivitas', '◷', 'admin.log'),
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
