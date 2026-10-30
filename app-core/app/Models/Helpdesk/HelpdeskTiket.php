<?php

declare(strict_types=1);

namespace App\Models\Helpdesk;

use App\Enums\PrioritasTiket;
use App\Enums\StatusTiket;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One request from a student, lecturer or member of staff to the campus.
 *
 * Audited like an academic record: who moved it, when, and to whom is exactly
 * what gets asked when a complaint about the complaint arrives.
 *
 * @property StatusTiket $status
 * @property PrioritasTiket $prioritas
 */
class HelpdeskTiket extends Model
{
    use DapatDicari;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'helpdesk_tiket';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /** The body is long and private; the trail needs the moves, not the prose. */
    protected array $logExcept = ['isi', 'lampiran_path', 'balasan_terakhir_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusTiket::class,
            'prioritas' => PrioritasTiket::class,
            'tenggat_sla_at' => 'datetime',
            'lewat_sla_at' => 'datetime',
            'didisposisi_at' => 'datetime',
            'ditanggapi_at' => 'datetime',
            'selesai_at' => 'datetime',
            'ditutup_at' => 'datetime',
            'balasan_terakhir_at' => 'datetime',
        ];
    }

    /** Mahasiswa, Dosen, or Staff filing to another unit. */
    public function pemohon(): MorphTo
    {
        return $this->morphTo();
    }

    /** The officer who typed the ticket in on the applicant's behalf, if any. */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_oleh_staff_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(HelpdeskKategori::class, 'helpdesk_kategori_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'petugas_staff_id');
    }

    public function balasan(): HasMany
    {
        return $this->hasMany(HelpdeskBalasan::class, 'helpdesk_tiket_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** Replies the applicant is allowed to see. */
    public function balasanPublik(): HasMany
    {
        return $this->balasan()->where('is_internal', false);
    }

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StatusTiket::Baru->value,
            StatusTiket::Didisposisi->value,
            StatusTiket::Diproses->value,
            StatusTiket::MenungguPemohon->value,
        ]);
    }

    /** Open, and waiting on the campus rather than on the applicant. */
    public function scopeMenungguKampus(Builder $query): Builder
    {
        return $query->whereIn('status', [
            StatusTiket::Baru->value,
            StatusTiket::Didisposisi->value,
            StatusTiket::Diproses->value,
        ]);
    }

    public function scopeMilik(Builder $query, Model $pemohon): Builder
    {
        return $query
            ->where('pemohon_type', $pemohon->getMorphClass())
            ->where('pemohon_id', $pemohon->getKey());
    }

    /** Whether the deadline has passed while the campus still owes an answer. */
    public function lewatSla(): bool
    {
        return $this->lewat_sla_at !== null
            || ($this->tenggat_sla_at !== null
                && $this->status->menungguKampus()
                && $this->tenggat_sla_at->isPast());
    }

    public function adaLampiran(): bool
    {
        return filled($this->lampiran_path);
    }

    /** Filed by this person. */
    public function milik(Model $aktor): bool
    {
        return $this->pemohon_type === $aktor->getMorphClass()
            && (int) $this->pemohon_id === (int) $aktor->getKey();
    }

    protected function activityLabel(): ?string
    {
        return $this->nomor;
    }
}
