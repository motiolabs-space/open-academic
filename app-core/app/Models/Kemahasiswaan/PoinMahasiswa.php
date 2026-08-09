<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\JenisPoin;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line in a student's conduct record.
 *
 * The point value and the ledger side are **copies** of what the catalogue said
 * when the line was recorded, not references to what it says now. A campus
 * re-pricing an award next year must not rewrite what last year's graduates
 * were credited with.
 */
class PoinMahasiswa extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'poin_mahasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /** Same reasoning as PoinKategori: the model must agree with the column. */
    protected $attributes = [
        'is_verified' => false,
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPoin::class,
            'tanggal' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(PoinKategori::class, 'poin_kategori_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'verified_by_staff_id');
    }

    /**
     * Rows that actually count.
     *
     * Both directions matter: an unverified achievement must not push somebody
     * over the graduation line, and an unverified allegation must not sit
     * against their name as though it were established.
     */
    public function scopeDiakui(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('is_verified', false)->whereNull('alasan_tolak');
    }

    public function scopeJenis(Builder $query, JenisPoin $jenis): Builder
    {
        return $query->where('jenis', $jenis->value);
    }

    public function ditolak(): bool
    {
        return !$this->is_verified && filled($this->alasan_tolak);
    }
}
