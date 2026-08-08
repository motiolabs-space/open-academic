<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\JenisBeasiswa;
use App\Enums\StatusPenerima;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A scholarship scheme.
 *
 * @property JenisBeasiswa $jenis
 * @property array<int, string>|null $komponen
 */
class Beasiswa extends Model
{
    use DapatDicari;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'beasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisBeasiswa::class,
            'komponen' => 'array',
            'persen' => 'integer',
            'nominal' => 'integer',
            'kuota' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function penerima(): HasMany
    {
        return $this->hasMany(BeasiswaPenerima::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Awards currently running — the number the quota is measured against. */
    public function jumlahAktif(): int
    {
        return $this->penerima()->where('status', StatusPenerima::Aktif->value)->count();
    }

    public function kuotaTersisa(): ?int
    {
        return $this->kuota === null ? null : max(0, $this->kuota - $this->jumlahAktif());
    }

    /** How the coverage reads on a screen. */
    public function cakupan(): string
    {
        return $this->persen !== null
            ? $this->persen.'% dari komponen yang dicakup'
            : 'Rp'.number_format((float) $this->nominal, 0, ',', '.').' per semester';
    }

    /**
     * Whether this scheme touches a given charge component.
     *
     * An empty component list means everything. A scholarship that pays tuition
     * but not the laboratory fee is ordinary, and treating "unspecified" as
     * "nothing" would make every scheme cover nothing at all.
     */
    public function mencakup(string $namaKomponen): bool
    {
        if (blank($this->komponen)) {
            return true;
        }

        foreach ($this->komponen as $pola) {
            if (str_contains(mb_strtolower($namaKomponen), mb_strtolower((string) $pola))) {
                return true;
            }
        }

        return false;
    }
}
