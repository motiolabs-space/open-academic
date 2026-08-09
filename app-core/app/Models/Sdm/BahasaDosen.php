<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One row of a lecturer's bahasa_dosen history. */
class BahasaDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'bahasa_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
