<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Traits\HasLogAktivitas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmbBerkas extends Model
{
    use HasFactory;
    use HasLogAktivitas;

    protected $table = 'pmb_berkas';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    /**
     * Sent back by the admissions desk: not accepted, with a reason.
     *
     * No status column — the existing pair says it already. A note on an
     * unverified document is a request to replace it; a verified one needs no
     * note.
     */
    public function dikembalikan(): bool
    {
        return !$this->is_verified && filled($this->catatan);
    }

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftar::class, 'pmb_pendaftar_id');
    }
}
