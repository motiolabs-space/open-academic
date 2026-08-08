<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\JabatanFungsional;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One rung on a lecturer's functional rank ladder.
 *
 * @property JabatanFungsional $jabatan
 */
class JabatanFungsionalDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'jabatan_fungsional_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jabatan' => JabatanFungsional::class,
            'tanggal_sk' => 'date',
            'tmt' => 'date',
            'angka_kredit_ratus' => 'integer',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    /** Credit as a decimal, for display only — never for arithmetic. */
    public function angkaKredit(): float
    {
        return $this->angka_kredit_ratus / 100;
    }

    public function berlaku(): bool
    {
        return $this->dosen_aktif_id !== null;
    }

    /**
     * Whether the recorded credit actually reaches this rank's threshold.
     *
     * Reported rather than enforced. A campus entering twenty years of history
     * will have decrees whose credit was awarded under a different scheme, and
     * refusing those rows would mean the history never gets entered — but a rung
     * standing on credit that does not add up is worth showing to whoever
     * eventually files it with the ministry.
     */
    public function angkaKreditMencukupi(): bool
    {
        return $this->angkaKredit() >= $this->jabatan->angkaKreditMinimum();
    }

    /**
     * The current rung.
     *
     * Named `aktif` rather than `berlaku` because a scope and an instance method
     * cannot share a name — PHP resolves `Model::berlaku()` to the instance
     * method and fails, which is a confusing way to learn about a collision.
     *
     * @param Builder<self> $query
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNotNull('dosen_aktif_id');
    }
}
