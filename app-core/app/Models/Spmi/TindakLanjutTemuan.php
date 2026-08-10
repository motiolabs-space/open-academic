<?php

declare(strict_types=1);

namespace App\Models\Spmi;

use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A corrective action, and its independent verification.
 *
 * Verified by somebody other than whoever carried it out — a correction
 * verified by its own author is not verification, only a second statement from
 * the same person.
 */
class TindakLanjutTemuan extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'tindak_lanjut_temuan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = ['is_terverifikasi' => false];

    protected function casts(): array
    {
        return [
            'target_selesai' => 'date',
            'tanggal_realisasi' => 'date',
            'is_terverifikasi' => 'boolean',
            'diverifikasi_at' => 'datetime',
        ];
    }

    public function temuan(): BelongsTo
    {
        return $this->belongsTo(TemuanAudit::class, 'temuan_audit_id');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diverifikasi_by_staff_id');
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_by_staff_id');
    }
}
