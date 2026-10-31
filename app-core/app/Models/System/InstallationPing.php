<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Enums\PeristiwaInstalasi;
use App\Enums\StatusPingInstalasi;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * One installation ping to Humanix, kept locally so the campus can read back
 * what was sent (ADR-043). Infrastructure, not academic data: it is itself the
 * log, so it carries no activity trail of its own.
 */
class InstallationPing extends Model
{
    use HasUuid;

    protected $table = 'installation_pings';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'event' => PeristiwaInstalasi::class,
            'status' => StatusPingInstalasi::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'http_status' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
