<?php

declare(strict_types=1);

namespace App\Models\Spmi;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One measurable indicator of a standard.
 *
 * `indikator_kunci` is optional on purpose: most quality standards are checked
 * by an auditor's eyes, not counted. Offering the link saves re-typing what the
 * system already knows; not requiring it keeps the rest honestly a judgement.
 */
class IndikatorStandar extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'indikator_standar';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['target' => 'decimal:2'];
    }

    public function standar(): BelongsTo
    {
        return $this->belongsTo(StandarMutu::class, 'standar_mutu_id');
    }

    public function dapatDihitung(): bool
    {
        return filled($this->indikator_kunci);
    }
}
