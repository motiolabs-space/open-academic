<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One planned week of a teaching plan. */
class RpsPertemuan extends Model
{
    use HasFactory;

    protected $table = 'rps_pertemuan';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'pertemuan_ke' => 'integer',
            'bobot' => 'integer',
        ];
    }

    public function rps(): BelongsTo
    {
        return $this->belongsTo(Rps::class);
    }

    /**
     * Actual meetings that delivered this planned session.
     *
     * Plural, and across classes: parallel classes each deliver it once, and a
     * single class may split one planned session over two meetings.
     */
    public function pertemuanKelas(): HasMany
    {
        return $this->hasMany(PertemuanKelas::class, 'rps_pertemuan_id');
    }
}
