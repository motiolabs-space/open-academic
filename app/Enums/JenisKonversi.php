<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where recognised credit came from.
 *
 * The two differ in what counts as evidence, not in what they produce. A
 * transfer has a transcript from an institution that graded the work; RPL has an
 * assessor here who judged that experience elsewhere met the same outcome.
 *
 * That difference matters for who may decide and what must be attached, which is
 * why it is recorded rather than flattened into "credit from elsewhere".
 */
enum JenisKonversi: string
{
    case Transfer = 'transfer';
    case Rpl = 'rpl';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer / Pindahan',
            self::Rpl => 'Rekognisi Pembelajaran Lampau',
        };
    }

    public function deskripsi(): string
    {
        return match ($this) {
            self::Transfer => 'Mata kuliah yang sudah ditempuh pada perguruan tinggi lain.',
            self::Rpl => 'Pengalaman kerja atau pelatihan yang dinilai setara dengan mata kuliah ini.',
        };
    }

    /**
     * Whether the source institution must be named.
     *
     * Required for a transfer — a transcript without an issuer verifies nothing.
     * Not required for RPL, where the source is often employment.
     */
    public function perluInstitusi(): bool
    {
        return $this === self::Transfer;
    }

    /** Short mark printed beside the row on a transcript. */
    public function tanda(): string
    {
        return $this === self::Transfer ? 'T' : 'R';
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
