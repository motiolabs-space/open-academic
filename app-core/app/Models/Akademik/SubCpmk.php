<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One part of a CPMK — what a stretch of the course delivers, weighted within
 * its parent. The weekly plan names these; grade components measure them.
 */
class SubCpmk extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'sub_cpmk';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['bobot' => 'integer'];
    }

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class);
    }
}
