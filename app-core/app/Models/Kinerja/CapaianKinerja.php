<?php

declare(strict_types=1);

namespace App\Models\Kinerja;

use App\Enums\SumberRealisasi;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One check-in on one measure. */
class CapaianKinerja extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'capaian_kinerja';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'sumber' => SumberRealisasi::class];
    }

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(UkuranKinerja::class, 'ukuran_kinerja_id');
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_by_staff_id');
    }
}
