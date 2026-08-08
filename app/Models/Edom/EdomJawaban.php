<?php

declare(strict_types=1);

namespace App\Models\Edom;

use App\Models\Akademik\KelasKuliah;
use App\Models\Sdm\Dosen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer, belonging to nobody.
 *
 * Carries the class, the lecturer, and the question — everything needed to
 * average it — and no way at all to reach the person who wrote it.
 *
 * Answers are not grouped into responses either. Nothing needs to know which
 * answers arrived together, and a response identifier would let somebody
 * correlate one person's opinions across every question, which in a class of
 * seven is enough to reconstruct an individual from their pattern alone.
 */
class EdomJawaban extends Model
{
    use HasFactory;

    protected $table = 'edom_jawaban';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['nilai' => 'integer'];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(EdomPeriode::class, 'edom_periode_id');
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function pertanyaan(): BelongsTo
    {
        return $this->belongsTo(EdomPertanyaan::class, 'edom_pertanyaan_id');
    }
}
