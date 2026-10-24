<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One letter of one programme's grading scale, as PDDIKTI records it.
 *
 * The campus has a single scale in config/academic.php; PDDIKTI wants it
 * stated per programme, with the score band each letter covers and the date
 * it took effect. Rows are derived from the config for every programme that
 * has none yet (see SkalaPeriodeProdiService) and left alone afterwards, so a
 * programme whose scale must differ edits its rows rather than the config.
 */
class SkalaNilaiProdi extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'skala_nilai_prodi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'nilai_indeks' => 'decimal:2',
            'bobot_minimum' => 'decimal:2',
            'bobot_maksimum' => 'decimal:2',
            'berlaku_mulai' => 'date',
            'berlaku_sampai' => 'date',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }
}
