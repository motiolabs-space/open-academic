<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a notification is about, and whether the recipient may switch it off.
 *
 * The distinction that matters here is not importance — everything the system
 * sends feels important to whoever wrote it. It is **consequence**: whether
 * missing the message costs the person something the campus will later hold
 * them to.
 *
 * A study-plan rejection and an overdue invoice both end semesters. Offering an
 * off switch for those would let someone silence the only warning they get and
 * then be told they should have known. So the in-app record for those
 * categories is not optional.
 *
 * Email is always optional, for every category. It is delivery convenience; the
 * in-app record is the authoritative one.
 */
enum KategoriNotifikasi: string
{
    case Keuangan = 'keuangan';
    case Akademik = 'akademik';
    case TugasAkhir = 'tugas_akhir';
    case Kemahasiswaan = 'kemahasiswaan';

    /*
     * The lecturer's own employment record — workload assessment above all.
     *
     * Its own category rather than folded into Akademik, which is about
     * students. A BKD conclusion decides whether a certification allowance is
     * paid; a lecturer who muted it and then missed a returned report would lose
     * a semester's allowance over a preference they set once.
     */
    case Kepegawaian = 'kepegawaian';

    case Pengingat = 'pengingat';
    case Sistem = 'sistem';

    public function label(): string
    {
        return match ($this) {
            self::Keuangan => 'Keuangan',
            self::Akademik => 'Akademik',
            self::TugasAkhir => 'Tugas Akhir',
            self::Kemahasiswaan => 'Kemahasiswaan',
            self::Kepegawaian => 'Kepegawaian',
            self::Pengingat => 'Pengingat Tenggat',
            self::Sistem => 'Sistem & Integrasi',
        };
    }

    /**
     * The sentence a person reads on the preferences screen.
     *
     * Written from their side, not the system's: "what will I stop seeing", not
     * "which module emits this".
     */
    public function deskripsi(): string
    {
        return match ($this) {
            self::Keuangan => 'Tagihan terbit, pembayaran diterima, dan jatuh tempo.',
            self::Akademik => 'Keputusan rencana studi dan nilai yang sudah final.',
            self::TugasAkhir => 'Keputusan judul, penetapan pembimbing, dan jadwal ujian.',
            self::Kemahasiswaan => 'Keputusan cuti dan penetapan kelulusan.',
            self::Kepegawaian => 'Hasil penilaian beban kerja dosen (BKD) dan laporan yang dikembalikan asesor.',
            self::Pengingat => 'Pengingat sebelum tenggat: batas pengisian KRS, jatuh tempo tagihan, batas revisi.',
            self::Sistem => 'Kegagalan sinkronisasi Neo Feeder dan pengiriman webhook.',
        };
    }

    /**
     * Whether the in-app record may be switched off.
     *
     * False here does not mean unimportant — reminders are useful. It means
     * nobody loses a semester for having muted them, because the decision they
     * warn about arrives through a mandatory category anyway.
     */
    public function wajib(): bool
    {
        return match ($this) {
            self::Keuangan, self::Akademik, self::TugasAkhir,
            self::Kemahasiswaan, self::Kepegawaian => true,
            self::Pengingat, self::Sistem => false,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
