<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use App\Models\Sdm\Staff;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One reading of the campus Feeder's web-service dictionary.
 *
 * Infrastructure, not academic data: it records what Feeder said, and the
 * comparison with the mappers is derived from it whenever it is shown, so a
 * code change that fixes a field name shows up against the same reading.
 */
class FeederDictionaryRun extends Model
{
    use HasUuid;

    protected $table = 'feeder_dictionary_runs';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'diperiksa_at' => 'datetime',
        ];
    }

    public function aksi(): HasMany
    {
        return $this->hasMany(FeederDictionaryAct::class)->orderBy('act');
    }

    public function dijalankanOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dijalankan_by_staff_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
