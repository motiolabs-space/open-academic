<?php

declare(strict_types=1);

namespace App\Models\TugasAkhir;

use App\Enums\StatusProposal;
use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One version of a final-project proposal document (#38).
 *
 * Never edited after upload: a revision is the next version. The status
 * moves once, from Menunggu to the reviewer's decision.
 *
 * @property StatusProposal $status
 */
class Proposal extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'tugas_akhir_proposal';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusProposal::class,
            'versi' => 'integer',
            'diputus_at' => 'datetime',
        ];
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    public function calonPembimbing(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'calon_pembimbing_dosen_id');
    }

    public function telaah(): HasMany
    {
        return $this->hasMany(Telaah::class, 'tugas_akhir_proposal_id')->orderBy('id');
    }
}
