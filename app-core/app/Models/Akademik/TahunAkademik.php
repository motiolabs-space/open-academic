<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\SemesterType;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\TahunAkademikFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An academic term.
 *
 * `kode` is the PDDIKTI term code (20261 = odd semester of 2026/2027) and is
 * the value every Feeder payload and Bridge resource carries — not the id.
 *
 * The date windows on this row are the calendar gates: they decide when a
 * student may fill a KRS and when a lecturer may still enter grades.
 */
class TahunAkademik extends Model
{
    /** @use HasFactory<TahunAkademikFactory> */
    use HasFactory;

    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'tahun_akademik';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'semester' => SemesterType::class,

            // YEAR columns come back as strings from the driver.
            'tahun_mulai' => 'integer',

            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'krs_mulai' => 'date',
            'krs_selesai' => 'date',
            'krs_perubahan_selesai' => 'date',
            'nilai_mulai' => 'date',
            'nilai_selesai' => 'date',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function kelasKuliah(): HasMany
    {
        return $this->hasMany(KelasKuliah::class);
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class);
    }

    /** The single term flagged active, or null on a fresh installation. */
    public static function aktif(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public static function byKode(string $kode): ?self
    {
        return static::query()->where('kode', $kode)->first();
    }

    /** Whether students may currently submit or revise a KRS. */
    public function krsDibuka(): bool
    {
        if ($this->is_locked || $this->krs_mulai === null) {
            return false;
        }

        $today = Carbon::today();
        $tutup = $this->krs_perubahan_selesai ?? $this->krs_selesai;

        return $today->gte($this->krs_mulai) && ($tutup === null || $today->lte($tutup));
    }

    /** Whether lecturers may currently enter or finalise grades. */
    public function penilaianDibuka(): bool
    {
        if ($this->is_locked || $this->nilai_mulai === null) {
            return false;
        }

        $today = Carbon::today();

        return $today->gte($this->nilai_mulai)
            && ($this->nilai_selesai === null || $today->lte($this->nilai_selesai));
    }

    /** @param Builder<self> $query */
    public function scopeTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('kode');
    }

    protected static function newFactory(): TahunAkademikFactory
    {
        return TahunAkademikFactory::new();
    }
}
