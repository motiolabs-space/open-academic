<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a queued accounting document has got to.
 *
 * `Gagal` is deliberately terminal rather than "will retry forever". A document
 * that keeps retrying hides a mapping mistake — a chart-of-accounts code that
 * does not exist on the other side — behind a rising attempt counter that
 * nobody reads. Giving up puts it on the monitor screen where a person can fix
 * the cause and requeue it.
 */
enum StatusDokumenAkuntansi: string
{
    case Menunggu = 'menunggu';
    case Terkirim = 'terkirim';
    case Gagal = 'gagal';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Kirim',
            self::Terkirim => 'Terkirim',
            self::Gagal => 'Gagal',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Menunggu => 'info',
            self::Terkirim => 'success',
            self::Gagal => 'danger',
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
