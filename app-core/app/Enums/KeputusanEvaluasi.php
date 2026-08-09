<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a person decided about a finding.
 *
 * Separate from HasilEvaluasi because the numbers and the decision are separate
 * things, and conflating them is how a scheduled job ends somebody's degree at
 * three in the morning.
 *
 * The campus routinely decides against its own rule, and rightly: a student who
 * missed the credit threshold because of a term spent in hospital is a case for
 * a person, not for a comparison operator. That is why every decision carries a
 * note.
 */
enum KeputusanEvaluasi: string
{
    /** Recorded, nobody has looked yet. Every finding starts here. */
    case Menunggu = 'menunggu';

    /** Allowed to continue, whatever the numbers said. */
    case Lanjut = 'lanjut';

    /** Continues, formally warned. */
    case Peringatan = 'peringatan';

    /** Studies ended. Terminal, and only ever reached by a human. */
    case DropOut = 'drop_out';

    /** Left of their own accord after being counselled. */
    case MengundurkanDiri = 'mengundurkan_diri';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu keputusan',
            self::Lanjut => 'Diizinkan lanjut',
            self::Peringatan => 'Peringatan akademik',
            self::DropOut => 'Putus studi',
            self::MengundurkanDiri => 'Mengundurkan diri',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Menunggu => 'neutral',
            self::Lanjut => 'success',
            self::Peringatan => 'warning',
            self::DropOut, self::MengundurkanDiri => 'danger',
        };
    }

    /** Whether this decision ends the student's enrolment. */
    public function mengakhiriStudi(): bool
    {
        return $this === self::DropOut || $this === self::MengundurkanDiri;
    }

    /**
     * Whether this decision requires a written reason.
     *
     * Anything that ends a degree, and anything that overrides the campus's own
     * rule — because both are exactly the decisions somebody will be asked to
     * account for later.
     */
    public function wajibBeralasan(): bool
    {
        return $this !== self::Menunggu;
    }

    /** @return array<int, self> Decisions a person may record. */
    public static function dapatDipilih(): array
    {
        return [self::Lanjut, self::Peringatan, self::MengundurkanDiri, self::DropOut];
    }
}
