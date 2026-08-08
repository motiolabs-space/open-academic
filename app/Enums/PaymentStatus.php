<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * State of a single payment attempt against an invoice.
 * Values follow Midtrans transaction_status so gateway callbacks map directly.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Settlement = 'settlement';
    case Expire = 'expire';
    case Deny = 'deny';
    case Cancel = 'cancel';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Settlement => 'Berhasil',
            self::Expire => 'Kedaluwarsa',
            self::Deny => 'Ditolak',
            self::Cancel => 'Dibatalkan',
            self::Refund => 'Dikembalikan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Settlement => 'success',
            self::Expire, self::Cancel, self::Refund => 'neutral',
            self::Deny => 'danger',
        };
    }

    /** Only settled payments reduce the outstanding balance. */
    public function isSuccessful(): bool
    {
        return $this === self::Settlement;
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
