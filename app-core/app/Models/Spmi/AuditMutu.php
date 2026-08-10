<?php

declare(strict_types=1);

namespace App\Models\Spmi;

use App\Enums\StatusAudit;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One internal audit of one unit. */
class AuditMutu extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'audit_mutu';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = ['status' => StatusAudit::Direncanakan->value];

    protected function casts(): array
    {
        return [
            'status' => StatusAudit::class,
            'tanggal_audit' => 'date',
            'ditutup_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function auditorDosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'auditor_dosen_id');
    }

    public function auditorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'auditor_staff_id');
    }

    public function temuan(): HasMany
    {
        return $this->hasMany(TemuanAudit::class);
    }

    /** Whichever table the auditor lives in. At most one is ever set. */
    public function auditor(): Dosen|Staff|null
    {
        return $this->auditorDosen ?? $this->auditorStaff;
    }
}
