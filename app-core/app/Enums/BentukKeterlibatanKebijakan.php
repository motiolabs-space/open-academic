<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a lecturer took part in drafting a policy — the three forms IKU 8 of
 * Kepmendiktisaintek 358/M/KEP/2025 recognises (ADR-041 §1).
 *
 * A member of the drafting team; an official resource person or expert asked
 * for written input; or a contributor whose study or research was taken into
 * the issued policy document. Attending a socialisation of a policy that is
 * already issued is none of these, which is why the list is closed.
 */
enum BentukKeterlibatanKebijakan: string
{
    case TimPenyusun = 'tim_penyusun';
    case Narasumber = 'narasumber';
    case KontributorKajian = 'kontributor_kajian';

    public function label(): string
    {
        return __('portofolio.kebijakan.bentuk.'.$this->value);
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
