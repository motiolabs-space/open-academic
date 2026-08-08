<?php

declare(strict_types=1);

namespace App\Models\Surat;

use App\Enums\JenisSurat;
use App\Enums\StatusSurat;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One issued — or requested — official letter.
 *
 * @property JenisSurat $jenis
 * @property StatusSurat $status
 * @property array<string, mixed>|null $konten
 */
class Surat extends Model
{
    use DapatDicari;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'surat';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisSurat::class,
            'status' => StatusSurat::class,
            'konten' => 'array',
            'tahun' => 'integer',
            'berlaku_sampai' => 'date',
            'diajukan_at' => 'datetime',
            'diterbitkan_at' => 'datetime',
            'dicabut_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function penerbit(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diterbitkan_by_staff_id');
    }

    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('status', StatusSurat::Diterbitkan->value);
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', StatusSurat::Diajukan->value);
    }

    /**
     * Past its validity date.
     *
     * Expiry is a property of the document, not of the record: an expired
     * letter is still genuine and still verifies, it simply no longer asserts
     * anything about today.
     */
    public function kedaluwarsa(): bool
    {
        return $this->berlaku_sampai !== null && $this->berlaku_sampai->isPast();
    }

    /** Whether a reader should currently treat it as good. */
    public function berlaku(): bool
    {
        return $this->status === StatusSurat::Diterbitkan && !$this->kedaluwarsa();
    }

    /** A field from the frozen snapshot, never recomputed. */
    public function isi(string $kunci, mixed $bawaan = null): mixed
    {
        return data_get($this->konten, $kunci, $bawaan);
    }
}
