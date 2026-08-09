<?php

declare(strict_types=1);

namespace App\Models\Kuesioner;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An answer to an anonymous questionnaire.
 *
 * There is no respondent column and no relation to add one to. Anything that
 * wanted to identify these rows would have to invent the link, which is the
 * guarantee.
 */
class KuesionerJawabanAnonim extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_jawaban_anonim';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function pertanyaan(): BelongsTo
    {
        return $this->belongsTo(KuesionerPertanyaan::class, 'kuesioner_pertanyaan_id');
    }
}
