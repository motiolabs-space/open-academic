<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a graduate's employment status came from (ADR-037 §2).
 *
 * Recorded with every import, because the answers are not equally strong:
 * the national tracer study is what the ministry counts, Open Campus runs the
 * campus's own questionnaire, and a campus survey outside both is the weakest.
 */
enum SumberStatusPekerjaan: string
{
    case TracerNasional = 'tracer_nasional';
    case OpenCampus = 'open_campus';
    case SurveiKampus = 'survei_kampus';

    public function label(): string
    {
        return __('lulusan.sumber.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s): array => [$s->value => $s->label()])
            ->all();
    }
}
