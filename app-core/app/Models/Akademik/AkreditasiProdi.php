<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\LembagaAkreditasi;
use App\Enums\PeringkatAkreditasi;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One accreditation decree for a programme (ADR-041 §3).
 *
 * @property LembagaAkreditasi $lembaga
 * @property PeringkatAkreditasi $peringkat
 * @property CarbonInterface $tanggal_sk
 * @property CarbonInterface $tanggal_berlaku
 * @property CarbonInterface $tanggal_kedaluwarsa
 */
class AkreditasiProdi extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'akreditasi_prodi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'lembaga' => LembagaAkreditasi::class,
            'peringkat' => PeringkatAkreditasi::class,
            'tanggal_sk' => 'date',
            'tanggal_berlaku' => 'date',
            'tanggal_kedaluwarsa' => 'date',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_oleh_staff_id');
    }

    /** In force on that day, both ends inclusive. */
    public function berlakuPada(CarbonInterface $tanggal): bool
    {
        $hari = $tanggal->toDateString();

        return $this->tanggal_berlaku->toDateString() <= $hari
            && $hari <= $this->tanggal_kedaluwarsa->toDateString();
    }
}
