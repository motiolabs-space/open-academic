<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Enums\KategoriNotifikasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One person's choice about one category of notification.
 *
 * Rows exist only where somebody has changed something. The absence of a row is
 * the default, which is why a fresh installation notifies correctly without
 * anyone configuring anything.
 */
class PreferensiNotifikasi extends Model
{
    protected $table = 'preferensi_notifikasi';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriNotifikasi::class,
            'aplikasi' => 'boolean',
            'email' => 'boolean',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
