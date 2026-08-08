<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\AttendanceStatus;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One student's attendance mark for one meeting. */
class Presensi extends Model
{
    use HasFactory;
    use HasUuid;

    protected $table = 'presensi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'waktu_absen' => 'datetime',
        ];
    }

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(PertemuanKelas::class, 'pertemuan_kelas_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}
