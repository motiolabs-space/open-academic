<?php

declare(strict_types=1);

namespace App\Services\TugasAkhir;

use App\Enums\PeranPembimbing;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\Pembimbing;
use App\Models\TugasAkhir\TugasAkhir;
use App\Notifications\TugasAkhir\JudulDiputus;
use App\Notifications\TugasAkhir\PembimbingDitetapkan;
use App\Services\Notifikasi\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The final project: proposing a title, approving it, and assigning the people
 * responsible for the work.
 *
 * Two scarce things are allocated here and both are easy to over-allocate
 * without noticing. A student may only have one project running, and a lecturer
 * may only supervise so many. Neither limit announces itself when breached: the
 * first shows up as two competing titles at graduation, the second as a
 * supervisor with forty students and no time for any of them.
 */
class TugasAkhirService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Proposes a title.
     *
     * The credit floor is the substantive gate. Supervision is the scarcest
     * resource a department has, and a student who proposes in their second
     * year occupies a slot for years before there is anything to supervise.
     */
    public function ajukan(
        Mahasiswa $mahasiswa,
        TahunAkademik $term,
        string $judul,
        ?string $abstrak = null,
        ?string $bidangKajian = null,
    ): TugasAkhir {
        if ($mahasiswa->status !== StudentStatus::Aktif) {
            throw new AturanAkademikException(
                'Hanya mahasiswa berstatus aktif yang dapat mengajukan tugas akhir. Status saat ini: '
                    .$mahasiswa->status->label().'.',
            );
        }

        $minSks = (int) config('academic.tugas_akhir.min_sks_pengajuan');
        $sks = $mahasiswa->sksKumulatif();

        if ($minSks > 0 && $sks < $minSks) {
            throw new AturanAkademikException(sprintf(
                'Pengajuan tugas akhir memerlukan minimal %d SKS. Saat ini tercatat %d SKS.',
                $minSks,
                $sks,
            ));
        }

        $bulan = (int) config('academic.tugas_akhir.batas_bulan');

        try {
            return DB::transaction(function () use ($mahasiswa, $term, $judul, $abstrak, $bidangKajian, $bulan): TugasAkhir {
                // Locked so the check below and the insert cannot be
                // interleaved by a second request. The unique index on
                // mahasiswa_aktif_id is the real guarantee — this exists so the
                // common case produces a readable message instead of an
                // integrity violation.
                $berjalan = TugasAkhir::query()
                    ->where('mahasiswa_aktif_id', $mahasiswa->id)
                    ->lockForUpdate()
                    ->first();

                if ($berjalan !== null) {
                    throw new AturanAkademikException(sprintf(
                        'Mahasiswa ini sudah memiliki tugas akhir yang berjalan ("%s", %s). '
                            .'Selesaikan atau batalkan yang lama sebelum mengajukan judul baru.',
                        $berjalan->judul,
                        $berjalan->status->label(),
                    ));
                }

                return TugasAkhir::create([
                    'mahasiswa_id' => $mahasiswa->id,
                    'tahun_akademik_id' => $term->id,
                    'mahasiswa_aktif_id' => $mahasiswa->id,
                    'judul' => $judul,
                    'abstrak' => $abstrak,
                    'bidang_kajian' => $bidangKajian,
                    'status' => TugasAkhirStatus::Diajukan,
                    'tanggal_pengajuan' => now()->toDateString(),
                    'batas_selesai' => $bulan > 0 ? now()->addMonths($bulan)->toDateString() : null,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            /*
             * The lock above covers concurrent writes inside one database, but
             * the index is what actually holds under every deployment shape.
             * Translating it here means the losing request reads the same
             * sentence as the ordinary case rather than a driver error.
             *
             * Caught by Laravel's own exception type. An earlier version of
             * this method sniffed SQLSTATE codes by hand and silently failed to
             * match, so the losing request got a raw driver error instead of
             * the sentence above — and the test missed it by writing straight
             * to the table rather than going through this method.
             */
            if (str_contains($e->getMessage(), 'mahasiswa_aktif_id')) {
                throw new AturanAkademikException(
                    'Mahasiswa ini sudah memiliki tugas akhir yang berjalan.',
                );
            }

            throw $e;
        }
    }

    /** Approves the proposed title. Supervisors are assigned separately. */
    public function setujuiJudul(TugasAkhir $ta, Staff $staff): TugasAkhir
    {
        if ($ta->status !== TugasAkhirStatus::Diajukan) {
            throw new AturanAkademikException(
                'Hanya judul berstatus diajukan yang dapat disetujui. Status saat ini: '
                    .$ta->status->label().'.',
            );
        }

        $ta->update([
            'status' => TugasAkhirStatus::Disetujui,
            'tanggal_disetujui' => now()->toDateString(),
            'disetujui_by_staff_id' => $staff->id,
            'catatan' => null,
        ]);

        $this->notifier->kirim($ta->mahasiswa, new JudulDiputus($ta->refresh()));

        return $ta->refresh();
    }

    /**
     * Rejects the proposed title.
     *
     * The reason is mandatory and is shown to the student: a rejection without
     * one produces a second proposal with the same problem.
     */
    public function tolakJudul(TugasAkhir $ta, Staff $staff, string $alasan): TugasAkhir
    {
        if ($ta->status !== TugasAkhirStatus::Diajukan) {
            throw new AturanAkademikException(
                'Hanya judul berstatus diajukan yang dapat ditolak. Status saat ini: '
                    .$ta->status->label().'.',
            );
        }

        if (blank($alasan)) {
            throw new AturanAkademikException(
                'Penolakan judul wajib disertai alasan yang dapat dibaca mahasiswa.',
            );
        }

        // Releases the student's slot in the same write that closes the record,
        // so a rejected proposal never blocks the replacement.
        $ta->update([
            'status' => TugasAkhirStatus::Ditolak,
            'mahasiswa_aktif_id' => null,
            'disetujui_by_staff_id' => $staff->id,
            'catatan' => $alasan,
        ]);

        // The reason travels with the message — which is why this method refuses
        // to record a rejection without one.
        $this->notifier->kirim($ta->mahasiswa, new JudulDiputus($ta->refresh()));

        return $ta->refresh();
    }

    /**
     * Assigns a supervisor.
     *
     * Approving a title and finding someone to supervise it are separate events
     * at most campuses, sometimes weeks apart. The first assignment is what
     * moves the project into supervision — until then the student is approved
     * and waiting, which is a state worth being able to see.
     */
    public function tetapkanPembimbing(
        TugasAkhir $ta,
        Dosen $dosen,
        PeranPembimbing $peran = PeranPembimbing::Utama,
    ): Pembimbing {
        if (!in_array($ta->status, [TugasAkhirStatus::Disetujui, TugasAkhirStatus::Dibimbing], true)) {
            throw new AturanAkademikException(
                'Pembimbing hanya dapat ditetapkan setelah judul disetujui. Status saat ini: '
                    .$ta->status->label().'.',
            );
        }

        if (!$dosen->is_active) {
            throw new AturanAkademikException(
                "{$dosen->namaLengkap()} berstatus nonaktif dan tidak dapat ditetapkan sebagai pembimbing.",
            );
        }

        if ($ta->pembimbing()->where('dosen_id', $dosen->id)->exists()) {
            throw new AturanAkademikException(
                "{$dosen->namaLengkap()} sudah menjadi pembimbing pada tugas akhir ini.",
            );
        }

        if ($peran === PeranPembimbing::Utama
            && $ta->pembimbing()->where('peran', PeranPembimbing::Utama->value)->exists()
        ) {
            throw new AturanAkademikException(
                'Tugas akhir ini sudah memiliki pembimbing utama. Lepaskan yang lama lebih dulu, '
                    .'atau tetapkan dosen ini sebagai pembimbing pendamping.',
            );
        }

        $kuota = (int) config('academic.tugas_akhir.kuota_pembimbing');
        $beban = $this->bebanPembimbing($dosen);

        if ($kuota > 0 && $beban >= $kuota) {
            throw new AturanAkademikException(sprintf(
                '%s sudah membimbing %d tugas akhir yang berjalan, sama dengan batas %d. '
                    .'Bimbingan melampaui kuota berarti tidak ada yang benar-benar terbimbing.',
                $dosen->namaLengkap(),
                $beban,
                $kuota,
            ));
        }

        $pembimbing = DB::transaction(function () use ($ta, $dosen, $peran): Pembimbing {
            $pembimbing = Pembimbing::create([
                'tugas_akhir_id' => $ta->id,
                'dosen_id' => $dosen->id,
                'peran' => $peran,
                'ditetapkan_pada' => now()->toDateString(),
            ]);

            if ($ta->status === TugasAkhirStatus::Disetujui) {
                $ta->update(['status' => TugasAkhirStatus::Dibimbing]);
            }

            $ta->recordActivity(
                'supervisor_assigned',
                sprintf('%s ditetapkan sebagai %s.', $dosen->namaLengkap(), $peran->label()),
            );

            return $pembimbing;
        });

        /*
         * Both sides are told, and the message reads differently to each — the
         * student learns who to approach, the lecturer learns they have been
         * given work.
         *
         * This closes the gap the tugas akhir module could surface but not fix:
         * approval and assignment are separate events, sometimes weeks apart,
         * and until now neither party learned when the second one happened.
         */
        $this->notifier->kirimBanyak(
            [$ta->mahasiswa, $dosen],
            new PembimbingDitetapkan($pembimbing->refresh()),
        );

        return $pembimbing;
    }

    /**
     * Removes a supervisor.
     *
     * Refused once the work has concluded: the panel that examined it did so
     * under a particular supervision, and rewriting that afterwards changes the
     * provenance of a mark that has already been given.
     */
    public function lepasPembimbing(Pembimbing $pembimbing): void
    {
        $ta = $pembimbing->tugasAkhir;

        if ($ta->status === TugasAkhirStatus::Selesai) {
            throw new AturanAkademikException(
                'Tugas akhir ini sudah selesai. Susunan pembimbingnya melekat pada berita acara sidang '
                    .'dan tidak dapat diubah lagi.',
            );
        }

        DB::transaction(function () use ($pembimbing, $ta): void {
            $nama = $pembimbing->dosen->namaLengkap();
            $pembimbing->delete();

            // Back to waiting, not silently still "in supervision" with nobody
            // supervising — that is precisely the state this module exists to
            // make visible.
            if ($ta->pembimbing()->count() === 0 && $ta->status === TugasAkhirStatus::Dibimbing) {
                $ta->update(['status' => TugasAkhirStatus::Disetujui]);
            }

            $ta->recordActivity('supervisor_removed', "{$nama} dilepas dari pembimbing.");
        });
    }

    /**
     * Live projects a lecturer is supervising.
     *
     * Counted over running work only. A career total would refuse every
     * supervisor within a few years.
     */
    public function bebanPembimbing(Dosen $dosen): int
    {
        return Pembimbing::query()
            ->where('dosen_id', $dosen->id)
            ->whereHas('tugasAkhir', fn ($q) => $q->aktif())
            ->count();
    }

    /**
     * Supervision load for many lecturers at once, keyed by dosen id.
     *
     * The assignment screen lists every eligible lecturer with their current
     * load; asking per lecturer turns that into one query per row.
     *
     * @param array<int, int> $dosenIds
     * @return array<int, int>
     */
    public function bebanPembimbingBanyak(array $dosenIds): array
    {
        if ($dosenIds === []) {
            return [];
        }

        return Pembimbing::query()
            ->selectRaw('dosen_id, COUNT(*) as jumlah')
            ->whereIn('dosen_id', $dosenIds)
            ->whereHas('tugasAkhir', fn ($q) => $q->aktif())
            ->groupBy('dosen_id')
            ->pluck('jumlah', 'dosen_id')
            ->map(intval(...))
            ->all();
    }

    /**
     * Withdraws a running project and frees the student's slot.
     *
     * Deliberately not automatic on expiry. A project past its deadline is a
     * conversation between a student and a department; software that ends it
     * unilaterally just gets a fresh row created to work around the decision.
     */
    public function batalkan(TugasAkhir $ta, string $alasan): TugasAkhir
    {
        if (!$ta->status->aktif()) {
            throw new AturanAkademikException(
                'Hanya tugas akhir yang sedang berjalan yang dapat dibatalkan. Status saat ini: '
                    .$ta->status->label().'.',
            );
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Pembatalan tugas akhir wajib disertai alasan.');
        }

        DB::transaction(function () use ($ta, $alasan): void {
            $ta->update([
                'status' => TugasAkhirStatus::Dibatalkan,
                'mahasiswa_aktif_id' => null,
                'catatan' => $alasan,
            ]);

            $ta->recordActivity('cancelled', 'Tugas akhir dibatalkan. Alasan: '.$alasan);
        });

        return $ta->refresh();
    }

    /**
     * Closes the project once the manuscript is accepted.
     *
     * Requires a passed defence. Marking a project complete without one is how
     * a title reaches a diploma having never been examined — the exact failure
     * this module was built to remove.
     */
    public function selesaikan(TugasAkhir $ta, ?string $naskahPath = null): TugasAkhir
    {
        if ($ta->status === TugasAkhirStatus::Selesai) {
            throw new AturanAkademikException('Tugas akhir ini sudah dinyatakan selesai.');
        }

        if (!$ta->status->aktif()) {
            throw new AturanAkademikException(
                'Tugas akhir berstatus '.$ta->status->label().' tidak dapat diselesaikan.',
            );
        }

        $ta->loadMissing(['ujian.penguji']);
        $sidang = $ta->sidangLulus();

        if ($sidang === null) {
            throw new AturanAkademikException(
                'Belum ada sidang akhir yang dinyatakan lulus. Tugas akhir tidak dapat diselesaikan '
                    .'sebelum diuji — judul yang sampai ke ijazah tanpa sidang tidak dapat '
                    .'dipertanggungjawabkan.',
            );
        }

        DB::transaction(function () use ($ta, $sidang, $naskahPath): void {
            $ta->update([
                'status' => TugasAkhirStatus::Selesai,

                // The slot is released here, so a student who somehow needs a
                // second project later is not blocked by a finished one.
                'mahasiswa_aktif_id' => null,

                'nilai_akhir' => $sidang->nilai,
                'nilai_huruf' => $this->hurufUntuk((float) $sidang->nilai),
                'tanggal_selesai' => now()->toDateString(),
                'naskah_path' => $naskahPath ?? $ta->naskah_path,
            ]);

            $ta->recordActivity('completed', 'Tugas akhir dinyatakan selesai.');
        });

        return $ta->refresh();
    }

    /**
     * The institution's letter scale, read from the same config the course
     * grading uses so a defence is not marked on a private scale.
     */
    private function hurufUntuk(float $nilai): ?string
    {
        foreach ((array) config('academic.grading.scale') as $baris) {
            if ($nilai >= (float) $baris['min_score']) {
                return (string) $baris['letter'];
            }
        }

        return null;
    }
}
