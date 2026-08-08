<?php

declare(strict_types=1);

namespace App\DTOs\Sdm;

use App\Enums\UnsurBkd;

/**
 * One line of a workload sheet, before it is frozen into a report.
 *
 * Credit is carried in hundredths and never as a float, because these are summed
 * across a dozen lines and compared against a threshold that decides whether an
 * allowance is paid.
 */
readonly class BarisBeban
{
    public function __construct(
        public UnsurBkd $unsur,
        public string $kegiatan,
        public ?string $rincian,
        public int $sksRatus,

        /**
         * True when Open Academic derived this line from records it already
         * holds; false when a person typed it.
         *
         * This is the first thing an assessor needs: a derived line is checkable
         * against the class list in seconds, a self-reported one needs its
         * evidence opened.
         */
        public bool $otomatis = false,

        public ?int $penugasanId = null,
        public ?string $buktiPath = null,
    ) {}

    /** Display only. */
    public function sks(): float
    {
        return $this->sksRatus / 100;
    }
}
