<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery state of a Campus Bridge webhook.
 * "Exhausted" means every configured retry attempt failed.
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Exhausted = 'exhausted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Kirim',
            self::Delivered => 'Terkirim',
            self::Failed => 'Gagal (akan diulang)',
            self::Exhausted => 'Gagal Permanen',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Delivered => 'success',
            self::Failed => 'warning',
            self::Exhausted => 'danger',
        };
    }
}
