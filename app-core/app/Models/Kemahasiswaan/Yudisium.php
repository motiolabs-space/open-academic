<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The graduation decision for a student.
 *
 * Confirming it (status = ditetapkan) is what flips the student to
 * StudentStatus::Lulus, creates the alumni baseline record, and fires the
 * student.graduated webhook that Open Campus's tracer study (IKU 1) waits for.
 */
class Yudisium extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'yudisium';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_yudisium' => 'date',
            'tanggal_lulus' => 'date',
            'ipk' => 'decimal:2',
            'ditetapkan_at' => 'datetime',
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

    public function penetap(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'ditetapkan_by_staff_id');
    }

    public function pesertaWisuda(): HasMany
    {
        return $this->hasMany(WisudaPeserta::class);
    }

    /** Honours label derived from the configured GPA thresholds. */
    public static function predikatUntuk(float $ipk): ?string
    {
        foreach (config('academic.graduation.honours') as $row) {
            if ($ipk >= (float) $row['min_gpa']) {
                return $row['label'];
            }
        }

        return null;
    }
}
