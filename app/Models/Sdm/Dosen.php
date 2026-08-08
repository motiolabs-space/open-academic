<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\Penguji;
use App\Traits\AuthenticatesWithUuid;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\DosenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A lecturer. Authenticates on the "dosen" guard.
 *
 * The NIDN is the national identifier PDDIKTI keys on; it is nullable here
 * because practitioners brought in from industry (IKU 4) frequently have none,
 * and the Feeder pre-flight validator is what refuses to push such a row.
 */
class Dosen extends Authenticatable implements OAuthenticatable
{
    use AuthenticatesWithUuid;
    use DapatDicari;

    /** @use HasFactory<DosenFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasLogAktivitas;
    use HasRoles;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $hidden = ['password', 'remember_token'];

    protected string $guard_name = 'dosen';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'tanggal_lahir' => 'date',
            'jenis_kelamin' => Gender::class,
            'pendidikan_tertinggi' => EducationLevel::class,
            'is_praktisi' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Homebase study programme. */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /** Classes this lecturer teaches, in any role. */
    public function kelasKuliah(): BelongsToMany
    {
        return $this->belongsToMany(KelasKuliah::class, 'kelas_dosen')
            ->withPivot(['peran', 'porsi_sks', 'praktisi_instansi'])
            ->withTimestamps();
    }

    /** Students for whom this lecturer is the academic advisor (dosen wali). */
    public function mahasiswaBimbingan(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'dosen_wali_id');
    }

    /** External and non-teaching assignments — the IKU 3/4 source. */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanDosen::class);
    }

    /**
     * Final projects this lecturer supervises.
     *
     * Distinct from mahasiswaBimbingan() above, which is academic advising for
     * study plans. A lecturer is typically advisor to a whole cohort and
     * supervisor to a handful — conflating the two would make the supervision
     * quota meaningless.
     */
    public function pembimbingTugasAkhir(): HasMany
    {
        return $this->hasMany(Pembimbing::class);
    }

    public function pengujiTugasAkhir(): HasMany
    {
        return $this->hasMany(Penguji::class);
    }

    public function namaLengkap(): string
    {
        return trim(sprintf(
            '%s %s%s',
            $this->gelar_depan ?? '',
            $this->nama,
            $this->gelar_belakang ? ', '.$this->gelar_belakang : '',
        ));
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopePraktisi(Builder $query): Builder
    {
        return $query->where('is_praktisi', true);
    }

    protected static function newFactory(): DosenFactory
    {
        return DosenFactory::new();
    }
}
