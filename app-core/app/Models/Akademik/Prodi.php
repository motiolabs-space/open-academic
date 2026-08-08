<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\EducationLevel;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\ProdiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A study programme.
 *
 * `kode_pddikti` holds the id_sms assigned by PDDIKTI. Without it no Feeder
 * push for this programme can succeed, which is why the pre-flight validator
 * checks it before a sync run rather than failing halfway through one.
 */
class Prodi extends Model
{
    /** @use HasFactory<ProdiFactory> */
    use HasFactory;

    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'prodi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenjang' => EducationLevel::class,
            'is_active' => 'boolean',
        ];
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function kaprodi(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'kaprodi_dosen_id');
    }

    public function kurikulum(): HasMany
    {
        return $this->hasMany(Kurikulum::class);
    }

    public function kurikulumAktif(): HasOne
    {
        return $this->hasOne(Kurikulum::class)->where('is_active', true);
    }

    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class);
    }

    /**
     * Programme learning outcomes, stated on every diploma supplement.
     *
     * Belongs to the programme rather than to the graduate: written once, so the
     * English half is not left as a translation job on the morning of a
     * ceremony — which is when it stops happening.
     */
    public function cpl(): HasMany
    {
        return $this->hasMany(ProdiCpl::class)->orderBy('urutan')->orderBy('kode');
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class);
    }

    /** Lecturers whose homebase is this programme. */
    public function dosen(): HasMany
    {
        return $this->hasMany(Dosen::class);
    }

    public function namaLengkap(): string
    {
        return $this->jenjang->label().' '.$this->nama;
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): ProdiFactory
    {
        return ProdiFactory::new();
    }
}
