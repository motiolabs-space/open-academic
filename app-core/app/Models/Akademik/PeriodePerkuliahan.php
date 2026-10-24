<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One programme's teaching period for one term — PDDIKTI's "periode
 * perkuliahan".
 *
 * The campus calendar lives on tahun_akademik once for everyone; PDDIKTI
 * wants it per programme, with the intake figures of that term beside it.
 * Rows are derived from the term and the admission records for every
 * programme that has none (see SkalaPeriodeProdiService); a programme on a
 * different calendar edits its row.
 */
class PeriodePerkuliahan extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'periode_perkuliahan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_awal' => 'date',
            'tanggal_akhir' => 'date',
            'jumlah_minggu_pertemuan' => 'integer',
            'target_mahasiswa_baru' => 'integer',
            'pendaftar_ikut_seleksi' => 'integer',
            'pendaftar_lulus_seleksi' => 'integer',
            'daftar_ulang' => 'integer',
            'mengundurkan_diri' => 'integer',
            'metode_tatap_muka' => 'boolean',
            'metode_daring' => 'boolean',
            'metode_campuran' => 'boolean',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }
}
