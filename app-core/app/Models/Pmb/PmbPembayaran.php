<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Enums\PaymentStatus;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A form-fee payment recorded by the cashier.
 *
 * Never deleted: a reversal flips the status to Refund and keeps the row, the
 * same rule PembayaranService follows for tuition.
 */
class PmbPembayaran extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'pmb_pembayaran';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'dibatalkan_at' => 'datetime',
        ];
    }

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftar::class, 'pmb_pendaftar_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_oleh_id');
    }
}
