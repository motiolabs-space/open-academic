<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\KeputusanWaliData;
use App\DTOs\Akademik\RingkasanKrs;
use App\Enums\KrsStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Notifications\Akademik\KrsDiputus;
use App\Services\Bridge\BridgeEventPublisher;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * The study-plan engine — the single legitimate mutator of a KRS.
 *
 * Every rule an institution argues about lives here: how many credits the IPS
 * allows, whether prerequisites are cleared, whether a seat is still free,
 * whether the bill is paid enough to unlock enrolment, and who may approve.
 * Controllers call these methods and render the result; they never reproduce a
 * rule themselves, because a rule enforced in two places eventually disagrees
 * with itself.
 *
 * Status changes are events, not attribute writes: each transition is checked
 * against KrsStatus::canTransitionTo() and lands in the audit trail.
 */
class KrsService
{
    public function __construct(
        private readonly BatasSksCalculator $batasSks,
        private readonly PrasyaratChecker $prasyarat,
        private readonly BridgeEventPublisher $bridge,
        private readonly Notifier $notifier,
    ) {}

    /**
     * The student's plan for a term, created on first access.
     *
     * The credit ceiling and the IPS it came from are snapshotted at creation:
     * a grade correction landing mid-semester must not silently widen or narrow
     * a plan the advisor has already looked at.
     */
    public function bukaAtauAmbil(Mahasiswa $mahasiswa, ?TahunAkademik $term = null): Krs
    {
        $term = $this->wajibAdaTerm($term);

        $this->pastikanMahasiswaBolehMengisi($mahasiswa);

        $krs = Krs::firstWhere([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
        ]);

        if ($krs !== null) {
            return $krs;
        }

        $acuan = $this->batasSks->untuk($mahasiswa, $term);

        return Krs::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'semester_ke' => $this->batasSks->semesterKe($mahasiswa, $term),
            'status' => KrsStatus::Draft,
            'total_sks' => 0,
            'batas_sks' => $acuan['batas'],
            'ips_acuan' => $acuan['ips'],
        ]);
    }

    /**
     * Adds a class to the plan.
     *
     * The seat is claimed inside a transaction that locks the offering row, so
     * two students hitting "ambil" on the last seat at the same moment cannot
     * both win — the loser gets a clear refusal instead of a class of 41.
     */
    public function tambahKelas(Krs $krs, KelasKuliah $kelas): KrsDetail
    {
        $this->pastikanDapatDiubah($krs);
        $this->pastikanJendelaKrsTerbuka($krs->tahunAkademik);

        $mahasiswa = $krs->mahasiswa;
        $mataKuliah = $kelas->mataKuliah;

        if ($kelas->tahun_akademik_id !== $krs->tahun_akademik_id) {
            throw AturanAkademikException::kelasBukanSemesterIni();
        }

        if (!$this->dalamKurikulum($mahasiswa, $kelas)) {
            throw AturanAkademikException::kelasDiLuarKurikulum($mataKuliah->nama);
        }

        if ($this->sudahMengambilMataKuliah($krs, $kelas)) {
            throw AturanAkademikException::kelasSudahDiambil($mataKuliah->nama);
        }

        if ($this->prasyarat->sudahLulus($mahasiswa, $mataKuliah)) {
            throw AturanAkademikException::mataKuliahSudahLulus($mataKuliah->nama);
        }

        if (config('academic.krs.enforce_prerequisites')) {
            $belum = $this->prasyarat->belumTerpenuhi($mahasiswa, $mataKuliah);

            if ($belum !== []) {
                throw AturanAkademikException::prasyaratBelumTerpenuhi($mataKuliah->nama, $belum);
            }
        }

        if (($krs->total_sks + $kelas->sks) > $krs->batas_sks) {
            throw AturanAkademikException::melebihiBatasSks($krs->total_sks + $kelas->sks, $krs->batas_sks);
        }

        $this->pastikanTidakBentrok($krs, $kelas);

        return DB::transaction(function () use ($krs, $kelas): KrsDetail {
            /** @var KelasKuliah $terkunci */
            $terkunci = KelasKuliah::whereKey($kelas->id)->lockForUpdate()->firstOrFail();

            if ($terkunci->terisi >= $terkunci->kuota) {
                throw AturanAkademikException::kuotaHabis($terkunci->namaLengkap());
            }

            $detail = KrsDetail::create([
                'krs_id' => $krs->id,
                'kelas_kuliah_id' => $terkunci->id,
                'sks' => $terkunci->sks,
                'is_mengulang' => $this->pernahMengambil($krs->mahasiswa, $terkunci),
            ]);

            $terkunci->increment('terisi');
            $krs->increment('total_sks', $terkunci->sks);

            return $detail;
        });
    }

    /** Removes a class and releases its seat. */
    public function hapusKelas(Krs $krs, KrsDetail $detail): void
    {
        $this->pastikanDapatDiubah($krs);
        $this->pastikanJendelaKrsTerbuka($krs->tahunAkademik);

        DB::transaction(function () use ($krs, $detail): void {
            $kelas = KelasKuliah::whereKey($detail->kelas_kuliah_id)->lockForUpdate()->firstOrFail();

            $detail->delete();

            if ($kelas->terisi > 0) {
                $kelas->decrement('terisi');
            }

            $krs->decrement('total_sks', $detail->sks);
        });
    }

    /** Submits the plan for the academic advisor's decision. */
    public function ajukan(Krs $krs): Krs
    {
        $this->pastikanTransisi($krs, KrsStatus::Diajukan);
        $this->pastikanJendelaKrsTerbuka($krs->tahunAkademik);

        if ($krs->detail()->count() === 0) {
            throw AturanAkademikException::krsKosong();
        }

        if (($penghalang = $this->penghalangPengajuan($krs->mahasiswa, $krs->tahunAkademik)) !== null) {
            throw new AturanAkademikException($penghalang);
        }

        $krs->update([
            'status' => KrsStatus::Diajukan,
            'diajukan_at' => now(),

            // A resubmission supersedes the previous rejection note.
            'catatan_wali' => null,
        ]);

        return $krs->refresh();
    }

    /**
     * Records the advisor's decision.
     *
     * Only the student's assigned advisor may decide. A lecturer holding the
     * krs.approve permission but a different advisee list is refused here as
     * well as by the policy — the rule belongs to the domain, not only to the
     * HTTP layer.
     */
    public function putuskan(Krs $krs, Dosen $dosen, KeputusanWaliData $keputusan): Krs
    {
        $tujuan = $keputusan->disetujui ? KrsStatus::Disetujui : KrsStatus::Ditolak;

        $this->pastikanTransisi($krs, $tujuan);

        if ($krs->mahasiswa->dosen_wali_id !== $dosen->id) {
            throw AturanAkademikException::bukanDosenWali();
        }

        if (!$keputusan->disetujui && blank($keputusan->catatan)) {
            throw AturanAkademikException::catatanPenolakanWajib();
        }

        $krs->update([
            'status' => $tujuan,
            'catatan_wali' => $keputusan->catatan,
            'disetujui_at' => $keputusan->disetujui ? now() : null,
            'disetujui_by_dosen_id' => $dosen->id,
        ]);

        if ($keputusan->disetujui) {
            $this->catatStatusSemester($krs);

            // Open Campus keeps enrolment-derived features fresh from this
            // rather than polling the registry.
            $this->bridge->publish('krs.approved', [
                'mahasiswa_uuid' => $krs->mahasiswa->uuid,
                'nim' => $krs->mahasiswa->nim,
                'semester' => $krs->tahunAkademik->kode,
                'semester_ke' => $krs->semester_ke,
                'total_sks' => $krs->total_sks,
                'disetujui_oleh' => $dosen->uuid,
                'kelas' => $krs->detail()->with('kelasKuliah')->get()
                    ->map(fn ($d): array => [
                        'kelas_uuid' => $d->kelasKuliah->uuid,
                        'sks' => $d->sks,
                    ])->values()->all(),
            ]);
        }

        /*
         * The student who submitted this is the one person who cannot see the
         * decision without being told. Before notifications existed they found
         * out by logging in and looking — and a rejection nobody looks at
         * becomes a semester nobody attended.
         *
         * Sent after the writes above, and swallowed on failure by Notifier: an
         * unreachable mail server must not undo an approval that already
         * happened.
         */
        $this->notifier->kirim($krs->mahasiswa, new KrsDiputus($krs->refresh(), $keputusan->catatan));

        return $krs->refresh();
    }

    /** Numbers a screen needs, computed once. */
    public function ringkas(Krs $krs): RingkasanKrs
    {
        $krs->loadMissing('detail');

        $penghalang = $krs->status->isEditable()
            ? $this->penghalangPengajuan($krs->mahasiswa, $krs->tahunAkademik)
            : null;

        return RingkasanKrs::dari($krs, $penghalang);
    }

    /**
     * Why the student cannot submit yet, or null when nothing stands in the way.
     * Returned as a message rather than thrown so a screen can show it inline.
     */
    public function penghalangPengajuan(Mahasiswa $mahasiswa, TahunAkademik $term): ?string
    {
        if (!$term->krsDibuka()) {
            return AturanAkademikException::krsBelumDibuka()->getMessage();
        }

        if (!$mahasiswa->status->canEnroll()) {
            return AturanAkademikException::mahasiswaTidakAktif($mahasiswa->status->label())->getMessage();
        }

        $tagihan = Tagihan::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $term->id)
            ->first();

        // No invoice issued means there is nothing to settle — the gate only
        // exists to enforce a bill that was actually raised.
        if ($tagihan !== null && !$tagihan->memenuhiSyaratKrs()) {
            return AturanAkademikException::krsTerkunciPembayaran(
                (int) config('academic.krs.min_payment_percent'),
            )->getMessage();
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     | Guards
     |-------------------------------------------------------------------- */

    private function wajibAdaTerm(?TahunAkademik $term): TahunAkademik
    {
        $term ??= TahunAkademik::aktif();

        if ($term === null) {
            throw AturanAkademikException::semesterTidakAktif();
        }

        return $term;
    }

    private function pastikanMahasiswaBolehMengisi(Mahasiswa $mahasiswa): void
    {
        if (!$mahasiswa->status->canEnroll()) {
            throw AturanAkademikException::mahasiswaTidakAktif($mahasiswa->status->label());
        }
    }

    private function pastikanDapatDiubah(Krs $krs): void
    {
        if (!$krs->status->isEditable()) {
            throw AturanAkademikException::krsTidakDapatDiubah($krs->status->label());
        }
    }

    private function pastikanJendelaKrsTerbuka(TahunAkademik $term): void
    {
        if (!$term->krsDibuka()) {
            throw AturanAkademikException::krsBelumDibuka();
        }
    }

    private function pastikanTransisi(Krs $krs, KrsStatus $tujuan): void
    {
        if (!$krs->status->canTransitionTo($tujuan)) {
            throw AturanAkademikException::transisiTidakSah($krs->status->label(), $tujuan->label());
        }
    }

    /* ---------------------------------------------------------------------
     | Rule helpers
     |-------------------------------------------------------------------- */

    private function dalamKurikulum(Mahasiswa $mahasiswa, KelasKuliah $kelas): bool
    {
        if ($mahasiswa->kurikulum_id === null) {
            // Without a curriculum binding the only defensible check is the
            // programme itself.
            return $kelas->prodi_id === $mahasiswa->prodi_id;
        }

        return $kelas->mataKuliah
            ->kurikulum()
            ->where('kurikulum.id', $mahasiswa->kurikulum_id)
            ->exists();
    }

    private function sudahMengambilMataKuliah(Krs $krs, KelasKuliah $kelas): bool
    {
        return $krs->detail()
            ->whereHas(
                'kelasKuliah',
                fn ($query) => $query->where('mata_kuliah_id', $kelas->mata_kuliah_id),
            )
            ->exists();
    }

    private function pernahMengambil(Mahasiswa $mahasiswa, KelasKuliah $kelas): bool
    {
        return KrsDetail::query()
            ->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
            ->whereHas('kelasKuliah', fn ($query) => $query->where('mata_kuliah_id', $kelas->mata_kuliah_id))
            ->exists();
    }

    private function pastikanTidakBentrok(Krs $krs, KelasKuliah $kelas): void
    {
        $kelas->loadMissing('jadwal');

        $terpakai = $krs->detail()
            ->with(['kelasKuliah.jadwal', 'kelasKuliah.mataKuliah'])
            ->get()
            ->pluck('kelasKuliah');

        foreach ($kelas->jadwal as $calon) {
            foreach ($terpakai as $existing) {
                foreach ($existing->jadwal as $dipakai) {
                    if ($calon->bentrokDengan($dipakai)) {
                        throw AturanAkademikException::jadwalBentrok(
                            $existing->mataKuliah->nama,
                            $dipakai->rentangWaktu(),
                        );
                    }
                }
            }
        }
    }

    /**
     * An approved plan is what makes the student enrolled for the term, so the
     * per-term status row is created here rather than by a nightly job.
     */
    private function catatStatusSemester(Krs $krs): void
    {
        StatusMahasiswa::firstOrCreate(
            [
                'mahasiswa_id' => $krs->mahasiswa_id,
                'tahun_akademik_id' => $krs->tahun_akademik_id,
            ],
            [
                'status' => $krs->mahasiswa->status,
                'semester_ke' => $krs->semester_ke,
                'sks_semester' => $krs->total_sks,
                'sks_kumulatif' => 0,
                'ips' => 0,
                'ipk' => 0,
            ],
        );
    }
}
