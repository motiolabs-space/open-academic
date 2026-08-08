<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A weekly recurring slot for a class. Room clashes are detected by
 * overlapping (ruang_id, hari, jam) tuples across a term.
 */
class JadwalKuliah extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'jadwal_kuliah';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    public const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    protected function casts(): array
    {
        return [
            'jam_mulai' => 'string',
            'jam_selesai' => 'string',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    public function namaHari(): string
    {
        return self::HARI[$this->hari] ?? '-';
    }

    /** "Senin, 07.30 – 09.10 WIB" */
    public function rentangWaktu(): string
    {
        return sprintf(
            '%s, %s – %s WIB',
            $this->namaHari(),
            str_replace(':', '.', substr((string) $this->jam_mulai, 0, 5)),
            str_replace(':', '.', substr((string) $this->jam_selesai, 0, 5)),
        );
    }

    /** Whether this slot overlaps another on the same day. */
    public function bentrokDengan(self $other): bool
    {
        return $this->hari === $other->hari
            && $this->jam_mulai < $other->jam_selesai
            && $other->jam_mulai < $this->jam_selesai;
    }
}
