<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\Notifikasi;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, plus one column.
 *
 * The category is already inside the JSON payload, but a screen that filters by
 * it needs to do so in SQL. Reaching into the JSON works on MySQL and SQLite and
 * breaks on PostgreSQL, where the column is text and has no JSON operators — so
 * the value is written out to a column of its own as well.
 *
 * Duplicated rather than moved: the payload stays self-describing, so a
 * consumer reading the row alone still sees what it was about.
 */
class DatabaseKategoriChannel extends DatabaseChannel
{
    /** @return array<string, mixed> */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return parent::buildPayload($notifiable, $notification) + [
            'kategori' => $notification instanceof Notifikasi
                ? $notification->kategori()->value
                : null,
        ];
    }
}
