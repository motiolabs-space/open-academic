<?php

declare(strict_types=1);

namespace App\Models\Kuesioner;

use App\Enums\SasaranKuesioner;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        ];
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
