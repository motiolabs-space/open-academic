<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\LogbookData;
use App\Exceptions\AturanAkademikException;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapanganLogbook;
use App\Models\Sdm\Dosen;
use App\Services\Berkas\BerkasService;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The participant's field log and periodic reports, and the field
 * supervisor's reading of them.
 *
 * The same shape as a final-project consultation log: the student writes, the
 * supervisor signs off, and a signed-off entry is evidence — it can no longer
 * be withdrawn by the person it is evidence about.
 */
class LogbookKegiatanService
{
    public function __construct(private readonly BerkasService $berkas) {}

    public function catat(AktivitasMahasiswa $aktivitas, LogbookData $data): KegiatanLapanganLogbook
    {
        $aktivitas->loadMissing('kegiatanLapangan');
        $kegiatan = $aktivitas->kegiatanLapangan;

        if ($kegiatan === null) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.bukan_peserta'));
        }

        if ($aktivitas->kegiatan_lapangan_kelompok_id === null) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.logbook_tanpa_kelompok'));
        }

        $tanggal = Carbon::parse($data->tanggal)->startOfDay();

        if ($tanggal->lt($kegiatan->tanggal_mulai) || $tanggal->isFuture()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.logbook_tanggal', [
                'mulai' => Format::tanggal($kegiatan->tanggal_mulai),
            ]));
        }

        $path = $data->berkas !== null
            ? $this->berkas->simpan($data->berkas, 'kegiatan-lapangan/'.$kegiatan->uuid)
            : null;

        try {
            return KegiatanLapanganLogbook::create([
                'aktivitas_mahasiswa_id' => $aktivitas->id,
                'jenis' => $data->jenis,
                'tanggal' => $tanggal->toDateString(),
                'judul' => $data->judul,
                'uraian' => $data->uraian,
                'berkas_path' => $path,
                'berkas_nama' => $data->berkas?->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            $this->berkas->hapus($path);

            throw $e;
        }
    }

    /** Withdraws an entry the supervisor has not read yet. */
    public function hapus(KegiatanLapanganLogbook $logbook): void
    {
        if ($logbook->sudahDiperiksa()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.logbook_terperiksa'));
        }

        $logbook->delete();
    }

    /**
     * The field supervisor signs an entry off, optionally with a note back.
     *
     * Only the group's own supervisor: a lecturer supervising another group
     * has not seen this student's work.
     */
    public function periksa(KegiatanLapanganLogbook $logbook, Dosen $dosen, ?string $catatan = null): KegiatanLapanganLogbook
    {
        $logbook->loadMissing('aktivitas.kelompok');

        if ((int) $logbook->aktivitas->kelompok?->dosen_pembimbing_id !== (int) $dosen->id) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.bukan_pembimbing'));
        }

        if ($logbook->sudahDiperiksa()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.logbook_terperiksa'));
        }

        DB::transaction(function () use ($logbook, $dosen, $catatan): void {
            $logbook->update([
                'diperiksa_at' => now(),
                'diperiksa_by_dosen_id' => $dosen->id,
                'catatan_pembimbing' => filled($catatan) ? trim($catatan) : null,
            ]);
        });

        return $logbook->refresh();
    }
}
