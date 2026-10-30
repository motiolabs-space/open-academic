<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\StatusPekerjaanAlumni;
use App\Enums\SumberStatusPekerjaan;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Baseline alumni record, created when a graduation is confirmed.
 *
 * Deliberately thin: the tracer-study questionnaire and IKU scoring belong to
 * Open Campus, which reads these rows over GET /graduates and /iku-data. This
 * row holds the graduate's confirmed contact and the employment status a
 * tracer study found, with where that answer came from (ADR-037 §2). Do not
 * grow a survey engine here.
 */
class Alumni extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'alumni';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = [
        'kontak_tidak_dibagikan' => false,
    ];

    protected function casts(): array
    {
        return [
            'mulai_bekerja' => 'date',
            'status_pekerjaan' => StatusPekerjaanAlumni::class,
            'status_pekerjaan_sumber' => SumberStatusPekerjaan::class,
            'status_pekerjaan_per' => 'date',
            'status_pekerjaan_diimpor_at' => 'datetime',
            'masa_tunggu_bulan' => 'integer',
            'kontak_diverifikasi_at' => 'datetime',
            'kontak_tidak_dibagikan' => 'boolean',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function kontakDiverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'kontak_diverifikasi_by_staff_id');
    }
}
