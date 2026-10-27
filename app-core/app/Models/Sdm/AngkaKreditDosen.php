<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\SumberAngkaKredit;
use App\Enums\UnsurAngkaKredit;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of a lecturer's credit ledger.
 *
 * @property UnsurAngkaKredit $unsur
 * @property SumberAngkaKredit $sumber
 */
class AngkaKreditDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'angka_kredit_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'unsur' => UnsurAngkaKredit::class,
            'sumber' => SumberAngkaKredit::class,
            'tanggal' => 'date',
            'sks_ratus' => 'integer',
            'faktor_ratus' => 'integer',
            'nilai_ratus' => 'integer',
            'perkiraan' => 'boolean',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function laporanBkd(): BelongsTo
    {
        return $this->belongsTo(BkdLaporan::class, 'bkd_laporan_id');
    }

    public function pendidikan(): BelongsTo
    {
        return $this->belongsTo(RiwayatPendidikanDosen::class, 'riwayat_pendidikan_dosen_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_oleh');
    }

    /**
     * Whether this line counts towards the next rank rather than the current.
     *
     * Work completed after the TMT of the rank in force; everything before is
     * already inside that rank's cumulative credit. Degrees are compared by
     * year and only a later year counts: a degree finished in the year of the
     * appointment was almost certainly what earned it, and counting it again
     * would overstate readiness — the safer error is the other one.
     */
    public function sesudahTmt(?CarbonInterface $tmt): bool
    {
        if ($tmt === null) {
            return true;
        }

        return $this->sumber === SumberAngkaKredit::PendidikanFormal
            ? $this->tanggal->year > $tmt->year
            : $this->tanggal->gt($tmt);
    }

    /** Display only — arithmetic stays in hundredths. */
    public function nilai(): float
    {
        return $this->nilai_ratus / 100;
    }
}
