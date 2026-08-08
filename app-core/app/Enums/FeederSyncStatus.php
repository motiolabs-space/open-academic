<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of one entry in the Neo Feeder sync ledger.
 *
 * "Skipped" means the payload hash was unchanged since the last successful
 * push — the mechanism that makes re-running a sync idempotent.
 */
enum FeederSyncStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Running => 'Berjalan',
            self::Success => 'Berhasil',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati (tidak berubah)',
            self::Invalid => 'Tidak Lolos Validasi',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Running => 'info',
            self::Success => 'success',
            self::Failed, self::Invalid => 'danger',
            self::Skipped => 'warning',
        };
    }

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }
}
