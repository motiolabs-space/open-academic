<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\PaymentStatus;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One payment attempt against an invoice.
 *
 * `raw_response` keeps the gateway callback verbatim: reconciliation disputes
 * are settled against what the gateway actually said, not against our
 * interpretation of it.
 */
class Pembayaran extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pembayaran';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'nominal' => 'integer',
            'raw_response' => 'array',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    /** @param Builder<self> $query */
    public function scopeBerhasil(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Settlement->value);
    }
}
