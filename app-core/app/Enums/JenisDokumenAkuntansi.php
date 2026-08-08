<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of document is queued for the accounting system.
 *
 * Four, and the order they appear in is the order they must be sent: a contact
 * before the invoice that names it, an invoice before the journal that settles
 * it. `PengirimAkuntansi` resolves that at send time rather than modelling a
 * dependency graph, but the ordering is real and this is where it is written
 * down.
 */
enum JenisDokumenAkuntansi: string
{
    case Kontak = 'kontak';
    case Produk = 'produk';
    case Invoice = 'invoice';
    case Jurnal = 'jurnal';

    public function label(): string
    {
        return match ($this) {
            self::Kontak => 'Kontak Pelanggan',
            self::Produk => 'Produk / Jasa',
            self::Invoice => 'Invoice (Piutang)',
            self::Jurnal => 'Jurnal Umum',
        };
    }

    /** The easyERP v1 path this document is POSTed to. */
    public function endpoint(): string
    {
        return match ($this) {
            self::Kontak => 'contacts',
            self::Produk => 'products',
            self::Invoice => 'invoices',
            self::Jurnal => 'journals',
        };
    }

    /**
     * Whether this document moves money.
     *
     * Contacts and products are master data — pushing them twice is untidy but
     * harmless. Invoices and journals hit the ledger, which is why the
     * idempotency key matters for these two above all.
     */
    public function membukukan(): bool
    {
        return in_array($this, [self::Invoice, self::Jurnal], true);
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
