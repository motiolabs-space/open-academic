<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One act's entry in a dictionary reading: the fields Feeder listed for it,
 * or why none could be read.
 *
 * @property array<int, array{nama: string, wajib: bool, kunci: bool, tipe: ?string, keterangan: ?string}>|null $medan
 */
class FeederDictionaryAct extends Model
{
    protected $table = 'feeder_dictionary_acts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'medan' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(FeederDictionaryRun::class, 'feeder_dictionary_run_id');
    }

    public function terbaca(): bool
    {
        return $this->galat === null && $this->medan !== null;
    }
}
