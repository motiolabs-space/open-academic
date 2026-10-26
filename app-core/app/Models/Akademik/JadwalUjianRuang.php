<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One room an exam session uses, and the seats it gives that session.
 *
 * Seats belong to this row rather than to the room because exam spacing halves
 * a lecture hall, and only the registrar knows which halls are used that way.
 */
class JadwalUjianRuang extends Model
{
    use HasLogAktivitas;

    protected $table = 'jadwal_ujian_ruang';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'kursi' => 'integer',
            'urutan' => 'integer',
        ];
    }

    public function jadwalUjian(): BelongsTo
    {
        return $this->belongsTo(JadwalUjian::class);
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }
}
