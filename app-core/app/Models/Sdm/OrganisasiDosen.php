<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One row of a lecturer's organisasi_dosen history. */
class OrganisasiDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'organisasi_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
