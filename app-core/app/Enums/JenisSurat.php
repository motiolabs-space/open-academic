<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of letter the campus issues.
 *
 * The distinction that earns its keep here is `swalayan()`: whether the system
 * already knows the answer, or whether somebody has to decide.
 *
 * A certificate of enrolment is the highest-volume request at any BAAK counter,
 * and the campus is not exercising judgement when it grants one — it is reading
 * a status column out loud. Making a person queue for that is the queue this
 * module exists to remove. A letter of introduction is different: it commits the
 * institution's name to somebody else's project, and that is a decision.
 */
enum JenisSurat: string
{
    case AktifKuliah = 'aktif_kuliah';
    case KeteranganLulus = 'keterangan_lulus';
    case Pengantar = 'pengantar';
    case TranskripLegalisir = 'transkrip_legalisir';
    case Skpi = 'skpi';

    public function label(): string
    {
        return match ($this) {
            self::AktifKuliah => 'Surat Keterangan Aktif Kuliah',
            self::KeteranganLulus => 'Surat Keterangan Lulus',
            self::Pengantar => 'Surat Pengantar',
            self::TranskripLegalisir => 'Transkrip Legalisir',
            self::Skpi => 'Surat Keterangan Pendamping Ijazah',
        };
    }

    /** The short form used in a document number. */
    public function kode(): string
    {
        return match ($this) {
            self::AktifKuliah => 'SKAK',
            self::KeteranganLulus => 'SKL',
            self::Pengantar => 'SP',
            self::TranskripLegalisir => 'TRL',
            self::Skpi => 'SKPI',
        };
    }

    /**
     * Whether the system can issue this without a human deciding.
     *
     * True only where the campus is reporting a fact it already holds. Anything
     * that commits the institution to a judgement stays with a person.
     */
    public function swalayan(): bool
    {
        return $this === self::AktifKuliah;
    }

    /** Whether the applicant must say what the letter is for. */
    public function perluKeperluan(): bool
    {
        return $this === self::Pengantar;
    }

    /**
     * How long the letter remains valid, in days. Null means indefinitely.
     *
     * Letters asserting a current state expire, because the state does. A
     * certificate of enrolment issued in March is not evidence of anything in
     * September, and one without an end date will be presented as though it is.
     *
     * The supplement and a legalised transcript describe things that already
     * happened and cannot stop being true, so they do not expire.
     */
    public function masaBerlakuHari(): ?int
    {
        return match ($this) {
            self::AktifKuliah, self::Pengantar => 90,
            self::KeteranganLulus => 180,
            self::TranskripLegalisir, self::Skpi => null,
        };
    }

    /** The Blade view that renders this letter's PDF. */
    public function tampilan(): string
    {
        return 'pdf.surat.'.str_replace('_', '-', $this->value);
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

    /** Types a student may ask for themselves. SKPI is issued, never requested. */
    public static function dapatDiajukan(): array
    {
        return array_filter(self::cases(), fn (self $j): bool => $j !== self::Skpi);
    }
}
