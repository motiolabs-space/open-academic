<?php

declare(strict_types=1);

namespace App\Models\TugasAkhir;

use App\Enums\PeranPembimbing;
use App\Models\Sdm\Dosen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lecturer's supervision of one final project.
 *
 * @property PeranPembimbing $peran
 */
class Pembimbing extends Model
{
    use HasFactory;

    protected $table = 'tugas_akhir_pembimbing';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'peran' => PeranPembimbing::class,
            'ditetapkan_pada' => 'date',
        ];
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
