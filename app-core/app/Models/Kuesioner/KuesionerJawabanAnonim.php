<?php

declare(strict_types=1);

namespace App\Models\Kuesioner;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    protected $guarded = ['id'];

    /**
     * A random key and no clock.
     *
     * A sequential id or a timestamp would run in step with the participation
     * table and let anyone with database access line the two up. Neither is
     * needed: every reader of this table aggregates.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $jawaban): void {
            $jawaban->id ??= (string) Str::uuid();
        });
    }

    public function pertanyaan(): BelongsTo
    {
        return $this->belongsTo(KuesionerPertanyaan::class, 'kuesioner_pertanyaan_id');
    }
}
