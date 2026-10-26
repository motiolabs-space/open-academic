<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invigilator of one exam session: a lecturer or a member of staff.
 */
class JadwalUjianPengawas extends Model
{
    use HasLogAktivitas;

    protected $table = 'jadwal_ujian_pengawas';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function jadwalUjian(): BelongsTo
    {
        return $this->belongsTo(JadwalUjian::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function jadwalUjianRuang(): BelongsTo
    {
        return $this->belongsTo(JadwalUjianRuang::class);
    }

    /** The person, whichever kind they are. */
    public function orang(): Dosen|Staff|null
    {
        return $this->dosen ?? $this->staff;
    }

    public function namaOrang(): string
    {
        return $this->orang()?->namaLengkap() ?? '—';
    }

    /** "dosen:12" or "staff:4" — one key space for both kinds of person. */
    public function kunci(): string
    {
        return $this->dosen_id !== null ? 'dosen:'.$this->dosen_id : 'staff:'.$this->staff_id;
    }
}
