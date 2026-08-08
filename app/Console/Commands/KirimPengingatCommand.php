<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\KrsStatus;
use App\Enums\StatusUjian;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\Bimbingan;
use App\Models\TugasAkhir\Ujian;
use App\Notifications\Pengingat\BatasKrsMendekat;
use App\Notifications\Pengingat\BimbinganMenunggu;
use App\Notifications\Pengingat\RevisiMendekatiBatas;
use App\Notifications\Pengingat\TagihanJatuhTempo;
use App\Services\Notifikasi\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Deadline reminders. Meant to run once a day.
 *
 * Everything here is idempotent by construction: the job asks "is today one of
 * the configured offsets for this deadline?" and then claims a key naming the
 * person, the deadline, and the offset. Running it twice sends nothing twice;
 * running it after a week of outage sends only today's, not the backlog.
 *
 * That second property is deliberate. Catching up on a week of missed reminders
 * would bury the recipient in messages about deadlines that have already moved,
 * which is worse than the outage was.
 */
class KirimPengingatCommand extends Command
{
    protected $signature = 'openacademic:kirim-pengingat
                            {--kering : Hitung tanpa mengirim apa pun}';

    protected $description = 'Mengirim pengingat tenggat: tagihan, KRS, revisi tugas akhir, dan antrean bimbingan.';

    public function __construct(private readonly Notifier $notifier)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $kering = (bool) $this->option('kering');

        if ($kering) {
            $this->warn('Mode kering: tidak ada yang dikirim, dan tidak ada kunci yang diklaim.');
        }

        $hasil = [
            'Tagihan jatuh tempo' => $this->tagihan($kering),
            'Batas pengisian KRS' => $this->krs($kering),
            'Batas revisi tugas akhir' => $this->revisi($kering),
            'Antrean bimbingan' => $this->bimbingan($kering),
        ];

        $this->table(
            ['Pengingat', 'Terkirim'],
            collect($hasil)->map(fn (int $n, string $label): array => [$label, $n])->values()->all(),
        );

        $this->info('Total: '.array_sum($hasil).' pengingat.');

        return self::SUCCESS;
    }

    /** Outstanding invoices approaching, on, or past their due date. */
    private function tagihan(bool $kering): int
    {
        $offset = (array) config('notifikasi.pengingat.tagihan');
        $terkirim = 0;

        Tagihan::query()
            ->with('mahasiswa')
            ->belumLunas()
            ->whereHas('mahasiswa', fn ($q) => $q->where('status', StudentStatus::Aktif->value))
            ->chunkById(200, function ($daftar) use ($offset, $kering, &$terkirim): void {
                foreach ($daftar as $tagihan) {
                    $sisa = $this->hariMenuju($tagihan->jatuh_tempo);

                    if (!in_array($sisa, $offset, true)) {
                        continue;
                    }

                    $terkirim += (int) $this->kirim(
                        $kering,
                        $tagihan->mahasiswa,
                        "tagihan:{$tagihan->id}:h{$sisa}",
                        new TagihanJatuhTempo($tagihan, $sisa),
                    );
                }
            });

        return $terkirim;
    }

    /**
     * Students who have submitted nothing as the study-plan window closes.
     *
     * Narrowed to those with no plan, rather than sent to the whole cohort. A
     * reminder that also reaches the people who already complied is how a
     * channel becomes noise — and the students who most need this one are the
     * least likely to read a channel full of messages that did not apply.
     */
    private function krs(bool $kering): int
    {
        $term = TahunAkademik::aktif();

        if ($term === null || $term->krs_selesai === null) {
            return 0;
        }

        $sisa = $this->hariMenuju($term->krs_selesai);

        if (!in_array($sisa, (array) config('notifikasi.pengingat.krs'), true)) {
            return 0;
        }

        $terkirim = 0;

        Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereDoesntHave('krs', fn ($q) => $q
                ->where('tahun_akademik_id', $term->id)
                ->whereIn('status', [KrsStatus::Diajukan->value, KrsStatus::Disetujui->value]))
            ->chunkById(200, function ($daftar) use ($term, $sisa, $kering, &$terkirim): void {
                foreach ($daftar as $mahasiswa) {
                    $terkirim += (int) $this->kirim(
                        $kering,
                        $mahasiswa,
                        "krs:{$term->id}:h{$sisa}",
                        new BatasKrsMendekat($term, $sisa),
                    );
                }
            });

        return $terkirim;
    }

    /**
     * Revisions owed after a defence that has not been closed out.
     *
     * Goes to the supervisor as well as the student: the deadline is theirs to
     * enforce, and a student chasing a supervisor who does not know it exists is
     * the usual shape of this failure.
     */
    private function revisi(bool $kering): int
    {
        $offset = (array) config('notifikasi.pengingat.revisi');
        $terkirim = 0;

        Ujian::query()
            ->with(['tugasAkhir.mahasiswa', 'tugasAkhir.pembimbing.dosen'])
            ->where('status', StatusUjian::Selesai->value)
            ->whereNotNull('batas_revisi')
            ->whereHas('tugasAkhir', fn ($q) => $q->where('status', '!=', TugasAkhirStatus::Selesai->value))
            ->chunkById(200, function ($daftar) use ($offset, $kering, &$terkirim): void {
                foreach ($daftar as $ujian) {
                    $sisa = $this->hariMenuju($ujian->batas_revisi);

                    if (!in_array($sisa, $offset, true)) {
                        continue;
                    }

                    $penerima = [
                        $ujian->tugasAkhir->mahasiswa,
                        ...$ujian->tugasAkhir->pembimbing->pluck('dosen')->all(),
                    ];

                    foreach (array_filter($penerima) as $orang) {
                        $terkirim += (int) $this->kirim(
                            $kering,
                            $orang,
                            "revisi:{$ujian->id}:h{$sisa}",
                            new RevisiMendekatiBatas($ujian, $sisa),
                        );
                    }
                }
            });

        return $terkirim;
    }

    /**
     * Consultation logs a supervisor has left unsigned.
     *
     * One digest per lecturer per week, not one message per pending entry. The
     * key carries the ISO week, so the same queue produces at most one reminder
     * in any week however often this runs.
     */
    private function bimbingan(bool $kering): int
    {
        $usia = (int) config('notifikasi.pengingat.bimbingan_menunggu_hari');
        $pekan = Carbon::now()->format('o-\WW');

        $antrean = Bimbingan::query()
            ->selectRaw('dosen_id, COUNT(*) as jumlah, COUNT(DISTINCT tugas_akhir_id) as mahasiswa')
            ->where('disetujui', false)
            ->whereDate('tanggal', '<=', now()->subDays($usia)->toDateString())
            ->groupBy('dosen_id')
            ->get();

        if ($antrean->isEmpty()) {
            return 0;
        }

        $dosen = Dosen::query()
            ->whereIn('id', $antrean->pluck('dosen_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $terkirim = 0;

        foreach ($antrean as $baris) {
            $orang = $dosen->get((int) $baris->dosen_id);

            if ($orang === null) {
                continue;
            }

            $terkirim += (int) $this->kirim(
                $kering,
                $orang,
                "bimbingan-menunggu:{$pekan}",
                new BimbinganMenunggu((int) $baris->jumlah, (int) $baris->mahasiswa),
            );
        }

        return $terkirim;
    }

    /** Whole days from today to the deadline; negative once it has passed. */
    private function hariMenuju(Carbon $tenggat): int
    {
        return (int) now()->startOfDay()->diffInDays($tenggat->copy()->startOfDay(), false);
    }

    private function kirim(bool $kering, object $penerima, string $kunci, $notifikasi): bool
    {
        // Dry runs claim nothing. A rehearsal that consumed the keys would
        // silence the real run that followed it.
        return $kering || $this->notifier->kirimSekali($penerima, $kunci, $notifikasi);
    }
}
