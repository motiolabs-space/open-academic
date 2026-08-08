<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\FakultasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fakultas extends Model
{
    /** @use HasFactory<FakultasFactory> */
    use HasFactory;

    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'fakultas';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    public function prodi(): HasMany
    {
        return $this->hasMany(Prodi::class);
    }

    public function dekan(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dekan_dosen_id');
    }

    protected static function newFactory(): FakultasFactory
    {
        return FakultasFactory::new();
    }
}
