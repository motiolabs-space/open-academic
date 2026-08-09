<?php

declare(strict_types=1);

namespace App\Models\Kuesioner;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * That somebody answered — never what they said.
 *
 * The gate and the response rate both need this fact. Neither needs the
 * content, and keeping them in separate tables is what makes an anonymous
 * questionnaire anonymous rather than merely promised to be.
 */
class KuesionerPartisipasi extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_partisipasi';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['diisi_at' => 'datetime'];
    }

    public function kuesioner(): BelongsTo
    {
        return $this->belongsTo(Kuesioner::class);
    }

    public function responden(): MorphTo
    {
        return $this->morphTo();
    }
}
