<?php

declare(strict_types=1);

namespace App\Models\Spmi;

use App\Enums\StatusTemuan;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One audit finding.
 *
 * Closed one way, and its text cannot be edited afterwards. That is what makes
 * this an internal audit rather than a task list: a finding that can be revised
 * after closing is a finding that can be smoothed over before a site visit.
 */
class TemuanAudit extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'temuan_audit';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = ['status' => StatusTemuan::Terbuka->value];

    protected function casts(): array
    {
        return [
            'status' => StatusTemuan::class,
            'tenggat' => 'date',
            'ditutup_at' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(AuditMutu::class, 'audit_mutu_id');
    }

    public function standar(): BelongsTo
    {
        return $this->belongsTo(StandarMutu::class, 'standar_mutu_id');
    }

    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjutTemuan::class, 'temuan_audit_id');
    }

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StatusTemuan::Terbuka->value,
            StatusTemuan::Ditindaklanjuti->value,
        ]);
    }

    public function ditutupOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'ditutup_by_staff_id');
    }

    /** @return array<string, mixed> */
    public function definisiJenis(): array
    {
        return (array) (config('spmi.jenis_temuan')[$this->jenis] ?? [
            'label' => $this->jenis,
            'tone' => 'neutral',
            'wajib_tindak_lanjut' => false,
            'tenggat_hari' => null,
        ]);
    }

    public function jenisLabel(): string
    {
        return (string) $this->definisiJenis()['label'];
    }

    public function tone(): string
    {
        return (string) $this->definisiJenis()['tone'];
    }

    /**
     * Whether this kind of finding must be corrected before it can be closed.
     *
     * Observations and suggestions may be closed without action. Demanding a
     * corrective plan for those makes auditors stop writing them down — and it
     * is exactly those lighter notes that turn out useful a year later.
     */
    public function wajibTindakLanjut(): bool
    {
        return (bool) $this->definisiJenis()['wajib_tindak_lanjut'];
    }

    /** Past its deadline and still not closed. */
    public function terlambat(): bool
    {
        return $this->tenggat !== null
            && $this->status !== StatusTemuan::Ditutup
            && $this->tenggat->isPast();
    }
}
