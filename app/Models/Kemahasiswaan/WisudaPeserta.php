<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WisudaPeserta extends Model
{
    use HasFactory;

    protected $table = 'wisuda_peserta';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(WisudaPeriode::class, 'wisuda_periode_id');
    }

    public function yudisium(): BelongsTo
    {
        return $this->belongsTo(Yudisium::class);
    }
}
