<?php

declare(strict_types=1);

namespace App\Models\Edom;

use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One term's evaluation window, with its own questions and its own threshold. */
class EdomPeriode extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'edom_periode';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'mulai' => 'date',
            'selesai' => 'date',
            'min_responden' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pertanyaan(): HasMany
    {
        return $this->hasMany(EdomPertanyaan::class)->orderBy('urutan')->orderBy('id');
    }

    public function partisipasi(): HasMany
    {
        return $this->hasMany(EdomPartisipasi::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether the window is open right now.
     *
     * Both flags matter: `is_active` is the campus's decision, the dates are the
     * schedule. A period left active past its close date must still refuse
     * submissions, or the window never really closes.
     */
    public function terbuka(): bool
    {
        return $this->is_active
            && !$this->mulai->isFuture()
            && !$this->selesai->copy()->endOfDay()->isPast();
    }

    /** The active period for the running term, if one is open. */
    public static function berjalan(): ?self
    {
        $term = TahunAkademik::aktif();

        if ($term === null) {
            return null;
        }

        return self::query()
            ->with('pertanyaan')
            ->where('tahun_akademik_id', $term->id)
            ->aktif()
            ->first();
    }
}
