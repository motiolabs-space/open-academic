<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One of the (by default) 16 meetings that make up a term for a class.
 *
 * QR attendance uses a rotating short-lived token so a screenshot forwarded to
 * an absent classmate stops working within minutes.
 */
class PertemuanKelas extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pertemuan_kelas';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'is_terlaksana' => 'boolean',
            'qr_expires_at' => 'datetime',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }

    public function qrAktif(): bool
    {
        return $this->qr_token !== null
            && $this->qr_expires_at !== null
            && $this->qr_expires_at->isFuture();
    }

    public function sisaDetikQr(): int
    {
        if (!$this->qrAktif()) {
            return 0;
        }

        // Carbon 3 mengembalikan float dari diffInSeconds().
        return max(0, (int) Carbon::now()->diffInSeconds($this->qr_expires_at, false));
    }
}
