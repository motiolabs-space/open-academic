<?php

declare(strict_types=1);

namespace App\Models\Edom;

use App\Enums\KategoriEdom;
use App\Enums\TipeJawabanEdom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question, belonging to one period.
 *
 * @property KategoriEdom $kategori
 * @property TipeJawabanEdom $tipe
 */
class EdomPertanyaan extends Model
{
    use HasFactory;

    protected $table = 'edom_pertanyaan';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriEdom::class,
            'tipe' => TipeJawabanEdom::class,
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(EdomPeriode::class, 'edom_periode_id');
    }
}
