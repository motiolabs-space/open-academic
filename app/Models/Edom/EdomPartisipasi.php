<?php

declare(strict_types=1);

namespace App\Models\Edom;

use App\Models\Akademik\KelasKuliah;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record that somebody completed an evaluation. Not what they said.
 *
 * This is the only table that knows a student's name in the whole module, and it
 * exists for exactly two jobs: releasing the enrolment gate, and refusing a
 * second submission.
 *
 * It has **no relation to EdomJawaban**, and adding one would undo the module's
 * central guarantee. If a future requirement seems to need the link — "let a
 * student edit their answers", "show me who complained" — the answer is that the
 * requirement is asking for de-anonymisation, and it should be refused rather
 * than accommodated.
 */
class EdomPartisipasi extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'edom_partisipasi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['diisi_at' => 'datetime'];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(EdomPeriode::class, 'edom_periode_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
