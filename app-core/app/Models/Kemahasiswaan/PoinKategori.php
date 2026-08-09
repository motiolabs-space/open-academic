<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\JenisPoin;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One entry in the campus catalogue of recognised achievements and violations.
 *
 * Editable from a screen, unlike the thresholds in config: this list is long,
 * campus-specific, and revised yearly by the people who administer it.
 */
class PoinKategori extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'poin_kategori';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /**
     * Mirrors the column defaults into the in-memory model.
     *
     * Without this, `create()` returns an instance whose `is_active` is still
     * null until something re-reads it — so the caller's very next line sees a
     * brand new category as inactive. Relying on a database default and then
     * reading the attribute back off the unrefreshed model is a trap for every
     * caller, not only the one that happened to find it.
     */
    protected $attributes = [
        'is_active' => true,
        'wajib_bukti' => true,
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPoin::class,
            'wajib_bukti' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(PoinMahasiswa::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function tingkatLabel(): string
    {
        return (string) (config('kemahasiswaan.tingkat')[$this->tingkat] ?? $this->tingkat ?? '-');
    }
}
