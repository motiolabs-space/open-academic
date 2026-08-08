<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Baseline alumni record, created when a graduation is confirmed.
 *
 * Deliberately thin: the tracer-study questionnaire and IKU 1 scoring belong
 * to Open Campus, which reads these rows over GET /graduates. Do not grow a
 * survey engine here.
 */
class Alumni extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'alumni';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'mulai_bekerja' => 'date',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}
