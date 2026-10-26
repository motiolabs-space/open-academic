<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Enums\JenisDaftar;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** An admission wave within an intake term. */
class PmbGelombang extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pmb_gelombang';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'biaya_pendaftaran' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pendaftar(): HasMany
    {
        return $this->hasMany(PmbPendaftar::class);
    }

    public function sedangBerjalan(): bool
    {
        $today = Carbon::today();

        return $this->is_active
            && $today->gte($this->tanggal_mulai)
            && $today->lte($this->tanggal_selesai);
    }

    /**
     * Whether applicants in this wave arrive with study done elsewhere.
     *
     * Their registration goes through the transfer intake instead of the
     * ordinary daftar ulang, which would record them as new entrants.
     */
    public function jalurPindahan(): bool
    {
        return in_array($this->jalur, ['transfer', 'rpl'], true);
    }

    /** The enrolment kind the intake form starts from for this wave. */
    public function jenisDaftarBawaan(): ?JenisDaftar
    {
        return match ($this->jalur) {
            'transfer' => JenisDaftar::Pindahan,
            'rpl' => JenisDaftar::RplPerolehan,
            default => null,
        };
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
