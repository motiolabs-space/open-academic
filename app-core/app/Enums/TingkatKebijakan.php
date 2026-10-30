<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whose policy it was.
 *
 * Not TingkatKegiatan: "industri" is not a reach between local and national
 * but a different kind of rule-maker — a sector standard or technical
 * regulation — and IKU 8 names it beside national and regional public
 * policy. International bodies are recorded too; the decree names only the
 * other three, so whether they count is the consumer's call (ADR-041 §1).
 */
enum TingkatKebijakan: string
{
    case Internasional = 'internasional';
    case Nasional = 'nasional';
    case Daerah = 'daerah';
    case Industri = 'industri';

    public function label(): string
    {
        return __('portofolio.kebijakan.tingkat.'.$this->value);
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
