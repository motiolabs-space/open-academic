<?php

declare(strict_types=1);

namespace App\Models\Kuesioner;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** An answer to a questionnaire that is deliberately not anonymous. */
class KuesionerJawaban extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_jawaban';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function pertanyaan(): BelongsTo
    {
        return $this->belongsTo(KuesionerPertanyaan::class, 'kuesioner_pertanyaan_id');
    }

    public function responden(): MorphTo
    {
        return $this->morphTo();
    }
}
