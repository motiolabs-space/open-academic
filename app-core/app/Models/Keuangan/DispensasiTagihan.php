<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\StatusDispensasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One payment dispensation on an invoice. Written by DispensasiService only.
 *
 * The live date sits on `tagihan.dispensasi_sampai`, which is what the KRS
 * gate and the late fee read; this row is who asked, who decided, and why.
 */
class DispensasiTagihan extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'dispensasi_tagihan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusDispensasi::class,
            'sampai' => 'date',
            'diputus_at' => 'datetime',
            'dicabut_at' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'diajukan_by_mahasiswa_id');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diputus_by_staff_id');
    }

    public function pencabut(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicabut_by_staff_id');
    }

    /** Granted, and its date has not yet passed. */
    public function masihBerlaku(): bool
    {
        return $this->status === StatusDispensasi::Disetujui
            && $this->sampai->gte(Carbon::today());
    }

    /** @param Builder<self> $query */
    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('status', StatusDispensasi::Disetujui->value)
            ->where('sampai', '>=', Carbon::today()->toDateString());
    }

    /** @param Builder<self> $query */
    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', StatusDispensasi::Diajukan->value);
    }
}
