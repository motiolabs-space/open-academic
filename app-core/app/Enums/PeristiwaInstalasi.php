<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why an installation ping was sent (ADR-043). There are only two: a ping is
 * never periodic, so "still running" is not something it can say.
 */
enum PeristiwaInstalasi: string
{
    case Dipasang = 'installed';
    case Diperbarui = 'upgraded';

    public function label(): string
    {
        return __('instalasi.telemetri.peristiwa.'.$this->value);
    }
}
