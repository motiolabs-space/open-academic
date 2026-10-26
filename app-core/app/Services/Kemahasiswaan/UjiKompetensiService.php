<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\HasilUjiKompetensiData;
use App\Enums\HasilUjiKompetensi;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\UjiKompetensi;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\UjiKompetensiBelumLulus;
use App\Services\Notifikasi\Notifier;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The stretch between a confirmed yudisium and a degree, for programmes that
 * require the national competency examination.
 *
 * Three things happen here and nowhere else:
 *
 *  - a sitting's result is recorded, and a passing one graduates the student
 *    through YudisiumService, which owns every graduation;
 *  - each term the student spends waiting gets its own per-term record with
 *    status MenungguUkom, which is what AktivitasKuliahMapper sends to
 *    PDDIKTI; without it the student would vanish from activity reporting
 *    and be counted as having dropped out;
 *  - the waiting list the yudisium screen shows.
 *
 * The status itself is set by YudisiumService::tetapkan(). Nothing here moves
 * a student into the waiting state; this service only moves them out.
 */
class UjiKompetensiService
{
    public function __construct(
        private readonly YudisiumService $yudisium,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Records one sitting.
     *
     * Failed sittings are recorded too, and change nothing else: the student
     * stays waiting and sits again. A passing one is final — the student is
     * Lulus afterwards, and a later result has nobody to apply to.
     */
    public function catatHasil(Mahasiswa $mahasiswa, HasilUjiKompetensiData $data, Staff $staff): UjiKompetensi
    {
        $mahasiswa->loadMissing(['prodi', 'yudisium']);

        if (!$mahasiswa->prodi->wajib_ukom) {
            throw new AturanAkademikException(__('ukom.galat.prodi_tidak_wajib', ['prodi' => $mahasiswa->prodi->nama]));
        }

        if ($mahasiswa->status !== StudentStatus::MenungguUkom || $mahasiswa->yudisium?->status !== 'ditetapkan') {
            throw new AturanAkademikException(__('ukom.galat.bukan_menunggu', [
                'nama' => $mahasiswa->nama,
                'status' => $mahasiswa->status->label(),
            ]));
        }

        if ($data->tanggalUjian->isAfter(today())) {
            throw new AturanAkademikException(__('ukom.galat.tanggal_depan'));
        }

        /*
         * The degree is complete on the later of the two dates.
         *
         * A sitting can precede the yudisium — final-year students may sit the
         * examination before their last grades are in — and the result is
         * only recordable afterwards. Graduating somebody on a date before
         * their yudisium would give PDDIKTI an exit date earlier than the
         * decision that allowed it.
         */
        $yudisium = $mahasiswa->yudisium;
        $tanggalLulus = $yudisium->tanggal_yudisium !== null && $data->tanggalUjian->lt($yudisium->tanggal_yudisium)
            ? Carbon::parse($yudisium->tanggal_yudisium)->startOfDay()
            : $data->tanggalUjian;

        $term = $this->semesterPada($tanggalLulus);

        $uji = DB::transaction(function () use ($mahasiswa, $data, $staff, $yudisium, $tanggalLulus, $term): UjiKompetensi {
            $uji = UjiKompetensi::create([
                'mahasiswa_id' => $mahasiswa->id,
                'tahun_akademik_id' => $term->id,
                'tanggal_ujian' => $data->tanggalUjian,
                'hasil' => $data->hasil,
                'nomor_sertifikat' => $data->nomorSertifikat,
                'catatan' => $data->catatan,
                'dicatat_by_staff_id' => $staff->id,
            ]);

            if ($data->hasil === HasilUjiKompetensi::Lulus) {
                $this->yudisium->lulusSetelahUjiKompetensi($yudisium, $staff, $tanggalLulus);
            }

            return $uji;
        });

        if ($data->hasil === HasilUjiKompetensi::TidakLulus) {
            $this->notifier->kirim($mahasiswa, new UjiKompetensiBelumLulus($uji));
        }

        return $uji;
    }

    /**
     * Students currently waiting, oldest yudisium first, with their sittings.
     *
     * @return EloquentCollection<int, Mahasiswa>
     */
    public function menunggu(): EloquentCollection
    {
        return Mahasiswa::query()
            ->with(['prodi', 'yudisium', 'ujiKompetensi' => fn ($q) => $q->latest('tanggal_ujian')])
            ->where('status', StudentStatus::MenungguUkom->value)
            ->get()
            ->sortBy(fn (Mahasiswa $m) => $m->yudisium?->tanggal_yudisium?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Writes the per-term record for every student waiting through this term.
     *
     * Frozen on creation: there are no grades to wait for. IPK and cumulative
     * credits carry over from the last frozen term, and the term itself has
     * no credits and no IPS — it is a term in which nothing was studied, and
     * the record says so rather than repeating the last semester's figures.
     *
     * Only for terms after the yudisium term. That term already has its own
     * record, with the courses the student actually took. Idempotent: a
     * student who already has a record for the term is left alone.
     *
     * @return int records created
     */
    public function catatSemesterMenunggu(TahunAkademik $term): int
    {
        $menunggu = Mahasiswa::query()
            ->where('status', StudentStatus::MenungguUkom->value)
            ->whereDoesntHave('statusPerSemester', fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->whereHas('yudisium', fn ($q) => $q
                ->where('status', 'ditetapkan')
                ->whereHas('tahunAkademik', fn ($t) => $t->where('kode', '<', $term->kode)))
            ->get();

        $dibuat = 0;

        foreach ($menunggu as $mahasiswa) {
            $terakhir = StatusMahasiswa::query()
                ->join('tahun_akademik', 'tahun_akademik.id', '=', 'status_mahasiswa.tahun_akademik_id')
                ->where('status_mahasiswa.mahasiswa_id', $mahasiswa->id)
                ->where('status_mahasiswa.is_final', true)
                ->where('tahun_akademik.kode', '<', $term->kode)
                ->orderByDesc('tahun_akademik.kode')
                ->select('status_mahasiswa.*')
                ->first();

            StatusMahasiswa::create([
                'mahasiswa_id' => $mahasiswa->id,
                'tahun_akademik_id' => $term->id,
                'status' => StudentStatus::MenungguUkom,
                'semester_ke' => min(255, (int) ($terakhir?->semester_ke ?? 0) + 1),
                'sks_semester' => 0,
                'sks_kumulatif' => (int) ($terakhir?->sks_kumulatif ?? 0),
                'ips' => 0,
                'ipk' => (float) ($terakhir?->ipk ?? 0),
                'keterangan' => __('ukom.akm_keterangan'),
                'is_final' => true,
                'finalized_at' => now(),
            ]);

            $dibuat++;
        }

        return $dibuat;
    }

    /** The term a date falls in; the active term when no term's dates cover it. */
    private function semesterPada(Carbon $tanggal): TahunAkademik
    {
        $term = TahunAkademik::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->orderByDesc('kode')
            ->first() ?? TahunAkademik::aktif();

        if ($term === null) {
            throw new AturanAkademikException(__('ukom.galat.tanpa_semester'));
        }

        return $term;
    }
}
