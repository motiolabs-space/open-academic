<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One course learning outcome (capaian pembelajaran mata kuliah).
 *
 * Rows of a version in force are never edited; see CpmkVersi.
 */
class Cpmk extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'cpmk';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    public function versi(): BelongsTo
    {
        return $this->belongsTo(CpmkVersi::class, 'cpmk_versi_id');
    }

    public function sub(): HasMany
    {
        return $this->hasMany(SubCpmk::class)->orderBy('urutan')->orderBy('kode');
    }

    /** Programme outcomes this CPMK contributes to, with its weight in each. */
    public function cpl(): BelongsToMany
    {
        return $this->belongsToMany(ProdiCpl::class, 'cpmk_cpl', 'cpmk_id', 'prodi_cpl_id')
            ->withPivot('bobot')
            ->withTimestamps();
    }
}
