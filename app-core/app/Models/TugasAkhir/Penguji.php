<?php

declare(strict_types=1);

namespace App\Models\TugasAkhir;

use App\Enums\PeranPenguji;
use App\Models\Sdm\Dosen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One seat on an examining panel, and the mark given from it.
 *
 * @property PeranPenguji $peran
 */
class Penguji extends Model
{
    use HasFactory;

    protected $table = 'tugas_akhir_penguji';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'peran' => PeranPenguji::class,
            'nilai' => 'decimal:2',
        ];
    }

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class, 'tugas_akhir_ujian_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
