<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why the matcher refused to adopt a student on its own.
 *
 * Each one is a different mistake waiting to happen. Adopting the wrong
 * record ties a student's grades, credits and eventually diploma number to
 * somebody else at PDDIKTI, and nothing downstream would notice.
 */
enum AlasanAmbiguAdopsi: string
{
    /** More than one Feeder row fits: by NIM, by NIK, or one of each. */
    case KandidatGanda = 'kandidat_ganda';

    /** Same NIM, different NIK: a reused number, or a different person. */
    case NikBerbeda = 'nik_berbeda';

    /** Same NIK, different NIM: the same person under a number this campus no longer uses. */
    case NimBerbeda = 'nim_berbeda';

    /** Same NIM, no NIK on one side to confirm it, and the names disagree. */
    case NamaBerbeda = 'nama_berbeda';

    /** The student already carries a different Feeder identifier. */
    case TertautLain = 'tertaut_lain';

    public function label(): string
    {
        return __('feeder.adopsi.alasan.'.$this->value);
    }
}
