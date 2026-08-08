<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WisudaPeriode extends Model
{
    use HasFactory;

    // Issuing diploma numbers is a one-way act on documents people keep for
    // life; who did it and when belongs in the trail.
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'wisuda_periode';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'is_pendaftaran_dibuka' => 'boolean',
        ];
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(WisudaPeserta::class);
    }

    public function sisaKuota(): ?int
    {
        return $this->kuota === null ? null : max(0, $this->kuota - $this->peserta()->count());
    }
}
