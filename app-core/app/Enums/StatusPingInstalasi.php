<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery state of one installation ping.
 *
 * "Cancelled" means consent was withdrawn, or HUMANIX_TELEMETRI=false set,
 * between queueing the ping and sending it — so it was never sent.
 */
enum StatusPingInstalasi: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('instalasi.telemetri.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'neutral',
            self::Sent => 'success',
            self::Failed => 'warning',
            self::Cancelled => 'neutral',
        };
    }
}
