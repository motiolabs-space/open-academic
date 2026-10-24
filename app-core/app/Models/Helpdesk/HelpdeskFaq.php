<?php

declare(strict_types=1);

namespace App\Models\Helpdesk;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A question the helpdesk answers before it is asked.
 *
 * Every published entry is one fewer ticket, which is the cheapest kind of
 * ticket there is. The public page shows them without a login, because the
 * people who most need "how do I reset my password" are the ones who cannot
 * sign in.
 */
class HelpdeskFaq extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'helpdesk_faq';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = [
        'urutan' => 0,
        'is_published' => false,
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(HelpdeskKategori::class, 'helpdesk_kategori_id');
    }

    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('id');
    }
}
