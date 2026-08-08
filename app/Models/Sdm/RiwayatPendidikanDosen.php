<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\EducationLevel;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One degree held by a lecturer.
 *
 * @property EducationLevel $jenjang
 */
class RiwayatPendidikanDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'riwayat_pendidikan_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenjang' => EducationLevel::class,
            'tahun_masuk' => 'integer',
            'tahun_lulus' => 'integer',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    /** A degree awarded abroad needs a recognition decree before it counts. */
    public function luarNegeri(): bool
    {
        return mb_strtolower($this->negara) !== 'indonesia';
    }
}
