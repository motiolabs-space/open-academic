<?php

declare(strict_types=1);

namespace App\Models\Akuntansi;

use App\Enums\JenisDokumenAkuntansi;
use App\Enums\StatusDokumenAkuntansi;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One document owed to the accounting system.
 *
 * @property JenisDokumenAkuntansi $jenis
 * @property StatusDokumenAkuntansi $status
 * @property array<string, mixed> $payload
 */
class DokumenAkuntansi extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'akuntansi_dokumen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisDokumenAkuntansi::class,
            'status' => StatusDokumenAkuntansi::class,
            'payload' => 'array',
            'nominal' => 'integer',
            'percobaan' => 'integer',
            'coba_lagi_setelah' => 'datetime',
            'terkirim_at' => 'datetime',
        ];
    }

    /** The invoice, payment, or student this came from. */
    public function sumber(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'lokal_type', 'lokal_id');
    }

    /**
     * Documents ready to go right now.
     *
     * Excludes anything still inside its backoff window. Ordered by id, which
     * is insertion order, so a contact created before its invoice is sent
     * before it too.
     *
     * @param Builder<self> $query
     */
    public function scopeSiapKirim(Builder $query): Builder
    {
        return $query
            ->where('status', StatusDokumenAkuntansi::Menunggu->value)
            ->where(fn (Builder $q) => $q
                ->whereNull('coba_lagi_setelah')
                ->orWhere('coba_lagi_setelah', '<=', now()))
            ->orderBy('id');
    }

    public function gagal(): bool
    {
        return $this->status === StatusDokumenAkuntansi::Gagal;
    }
}
