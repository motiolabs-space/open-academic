<?php

declare(strict_types=1);

namespace App\Models\Helpdesk;

use App\Models\Sdm\UnitKerja;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A kind of request, and where it goes by default.
 *
 * The category is what turns "somebody typed a complaint" into "the finance
 * office has a ticket": it names the unit and the response time promised.
 */
class HelpdeskKategori extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'helpdesk_kategori';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = [
        'urutan' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sla_jam' => 'integer',
            'urutan' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function tiket(): HasMany
    {
        return $this->hasMany(HelpdeskTiket::class, 'helpdesk_kategori_id');
    }

    public function faq(): HasMany
    {
        return $this->hasMany(HelpdeskFaq::class, 'helpdesk_kategori_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('nama');
    }

    /**
     * Hours promised, after the configured fallback. Null means no promise.
     */
    public function slaJamEfektif(): ?int
    {
        $jam = $this->sla_jam ?? config('helpdesk.sla_jam_bawaan');

        return $jam === null || (int) $jam <= 0 ? null : (int) $jam;
    }
}
