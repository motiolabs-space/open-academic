<?php

declare(strict_types=1);

namespace App\Models\Kuesioner;

use App\Enums\SasaranKuesioner;
use App\Enums\StatusKuesioner;
use App\Models\Akademik\Prodi;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One questionnaire.
 *
 * `anonim` decides which table the answers land in, and is fixed at creation.
 * See the migration for why it is not editable.
 */
class Kuesioner extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kuesioner';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = [
        'anonim' => true,
        'is_active' => false,
    ];

    protected function casts(): array
    {
        return [
            'sasaran' => SasaranKuesioner::class,
            'anonim' => 'boolean',
            'is_active' => 'boolean',
            'mulai' => 'date',
            'selesai' => 'date',
            'diterbitkan_at' => 'datetime',
            'min_responden' => 'integer',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dibuat_oleh_staff_id');
    }

    public function pertanyaan(): HasMany
    {
        return $this->hasMany(KuesionerPertanyaan::class)->orderBy('urutan');
    }

    public function partisipasi(): HasMany
    {
        return $this->hasMany(KuesionerPartisipasi::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Open questionnaires put to this person: their role, their programme (or
     * the whole campus), and today inside the window.
     */
    public function scopeUntuk(Builder $query, Model $responden): Builder
    {
        $sasaran = SasaranKuesioner::untuk($responden);

        if ($sasaran === null) {
            return $query->whereRaw('1 = 0');
        }

        $hari = now()->toDateString();

        return $query
            ->aktif()
            ->where('sasaran', $sasaran->value)
            ->where(fn (Builder $q) => $q->whereNull('prodi_id')
                ->when($sasaran->berprodi() && $responden->getAttribute('prodi_id') !== null,
                    fn (Builder $q) => $q->orWhere('prodi_id', $responden->getAttribute('prodi_id'))))
            ->where(fn (Builder $q) => $q->whereNull('mulai')->orWhereDate('mulai', '<=', $hari))
            ->where(fn (Builder $q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $hari));
    }

    /** Whether this person is in the audience, open or not. */
    public function menyasar(Model $responden): bool
    {
        if (SasaranKuesioner::untuk($responden) !== $this->sasaran) {
            return false;
        }

        return $this->prodi_id === null
            || (int) $this->prodi_id === (int) $responden->getAttribute('prodi_id');
    }

    public function status(): StatusKuesioner
    {
        return match (true) {
            $this->is_active => StatusKuesioner::Terbit,
            $this->diterbitkan_at !== null => StatusKuesioner::Ditutup,
            default => StatusKuesioner::Draf,
        };
    }

    /** Questions are frozen from the first publication on. */
    public function pertanyaanTerkunci(): bool
    {
        return $this->diterbitkan_at !== null;
    }

    /** Whether the window is open today, given it is active at all. */
    public function terbuka(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $hari = now()->startOfDay();

        return ($this->mulai === null || $hari->gte($this->mulai))
            && ($this->selesai === null || $hari->lte($this->selesai));
    }
}
