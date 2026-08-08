<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\LeaveStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A study-leave request. Approval flips the student's per-term status to
 * StudentStatus::Cuti, which is what eventually reaches PDDIKTI.
 */
class CutiMahasiswa extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'cuti_mahasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => LeaveStatus::class,
            'diajukan_at' => 'datetime',
            'diproses_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diproses_by_staff_id');
    }
}
