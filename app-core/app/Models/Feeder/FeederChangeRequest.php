<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use App\Enums\JalurPerubahanFeeder;
use App\Enums\JenisPerubahanFeeder;
use App\Enums\StatusPerubahanFeeder;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Feeder\KolomTerkunci;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A change to a column PDDIKTI locked when the student was reported.
 *
 * Created by the observer the moment such a column changes here, before
 * anyone decides what to do about it: the alternative — a sync that quietly
 * re-sends the biodata and is quietly refused — is how a campus ends up with
 * a graduate whose diploma names someone PDDIKTI has never heard of.
 *
 * One row per column: a name and a birth date corrected together are two
 * requests, because PDDIKTI admin processes them as two lines on the form.
 */
class FeederChangeRequest extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'feeder_change_requests';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPerubahanFeeder::class,
            'jalur' => JalurPerubahanFeeder::class,
            'status' => StatusPerubahanFeeder::class,
            'diajukan_at' => 'datetime',
            'dikirim_at' => 'datetime',
            'selesai_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diproses_by_staff_id');
    }

    /** Human label of the locked column, for the console and the letter. */
    public function labelKolom(): string
    {
        return KolomTerkunci::label($this->kolom);
    }

    /** Requests PDDIKTI has not yet confirmed. */
    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->where('status', '!=', StatusPerubahanFeeder::Selesai->value);
    }

    public function scopeJalur(Builder $query, JalurPerubahanFeeder $jalur): Builder
    {
        return $query->where('jalur', $jalur->value);
    }
}
