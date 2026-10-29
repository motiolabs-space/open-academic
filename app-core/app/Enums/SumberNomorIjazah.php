<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a diploma number comes from — `config('academic.ijazah.sumber')`.
 *
 * `pin` is the value this setting had before the national service became
 * PISN (ADR-036 §2). It is still read, as PISN, so an installation whose .env
 * says `pin` keeps the safe behaviour without a change; it is deprecated and
 * will stop being accepted in the next major release. Anything unrecognised
 * is also read as PISN: the default must be the one that cannot print an
 * unverifiable diploma by accident (ADR-013).
 */
enum SumberNomorIjazah: string
{
    /** Penomoran Ijazah dan Sertifikat Profesi Nasional: issued by the ministry, recorded here. */
    case Pisn = 'pisn';

    /** Generated here from a pattern, for institutions outside the national scheme. */
    case Lokal = 'lokal';

    /** The pre-PISN spelling of `pisn`, still accepted. */
    public const ALIAS_USANG = 'pin';

    public static function dariConfig(): self
    {
        return self::dari(config('academic.ijazah.sumber'));
    }

    public static function dari(mixed $nilai): self
    {
        return is_string($nilai) && strtolower(trim($nilai)) === self::Lokal->value
            ? self::Lokal
            : self::Pisn;
    }

    /** Whether the configured value is the deprecated alias. */
    public static function memakaiAliasUsang(): bool
    {
        $nilai = config('academic.ijazah.sumber');

        return is_string($nilai) && strtolower(trim($nilai)) === self::ALIAS_USANG;
    }
}
