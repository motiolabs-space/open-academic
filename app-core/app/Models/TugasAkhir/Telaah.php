<?php

declare(strict_types=1);

namespace App\Models\TugasAkhir;

use App\Enums\KeputusanTelaah;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reviewer's decision on one proposal version, with the note the student
 * reads. Written once and never changed.
 *
 * @property KeputusanTelaah $keputusan
 */
class Telaah extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'tugas_akhir_proposal_telaah';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['keputusan' => KeputusanTelaah::class];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'tugas_akhir_proposal_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function namaPenelaah(): string
    {
        return $this->dosen?->namaLengkap() ?? $this->staff?->nama ?? '—';
    }
}
