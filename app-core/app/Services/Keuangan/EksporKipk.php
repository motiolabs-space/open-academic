<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaPenerima;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The per-term report Puslapdik asks for about KIP Kuliah recipients.
 *
 * Every figure it needs — still enrolled, credits taken, IPS, IPK — is already
 * in `status_mahasiswa`. What the application does not know is which of its
 * scholarship schemes *is* KIP Kuliah, so that comes from config and the export
 * refuses to run until it is set.
 *
 * Three things this reports rather than smooths over, because each is a person
 * whose funding depends on it:
 *
 *  1. **A recipient with no status row for the term.** They were never enrolled
 *     that semester, or the row was never created. Dropping them from the file
 *     is how somebody keeps receiving a stipend while nobody notices they
 *     stopped studying.
 *
 *  2. **Figures that are not final yet.** `is_final` false means grades are
 *     still moving. Reporting them without saying so submits a number that the
 *     campus will contradict a fortnight later.
 *
 *  3. **A scheme that was never configured.** Refused outright — see
 *     config/kipk.php for why an empty file is the worse answer.
 *
 * Household income and home address are never included. KIP Kuliah is
 * means-tested, which makes them the most tempting fields to add and the ones
 * that must not travel in a CSV that circulates by email.
 */
class EksporKipk
{
    /** Whether a KIP Kuliah scheme has been identified at all. */
    public function siap(): bool
    {
        return $this->kodeSkema() !== [];
    }

    /**
     * Schemes recognised as KIP Kuliah, and whether each actually exists.
     *
     * Returned per code so a typo in config shows up as "kode ini tidak ada"
     * rather than as a report with nobody in it.
     *
     * @return array<string, string|null> kode => nama skema, atau null bila tak ada
     */
    public function skema(): array
    {
        $ada = Beasiswa::query()
            ->whereIn('kode', $this->kodeSkema())
            ->pluck('nama', 'kode')
            ->all();

        $hasil = [];

        foreach ($this->kodeSkema() as $kode) {
            $hasil[$kode] = $ada[$kode] ?? null;
        }

        return $hasil;
    }

    /**
     * One row per recipient, for one term.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function baris(TahunAkademik $term): Collection
    {
        if (!$this->siap()) {
            throw new RuntimeException(
                'Skema KIP Kuliah belum ditetapkan pada config/kipk.php, '
                .'jadi penerimanya tidak dapat dikenali.',
            );
        }

        $penerima = BeasiswaPenerima::query()
            ->with(['mahasiswa.prodi', 'beasiswa', 'mulai', 'selesai'])
            ->whereHas('beasiswa', fn ($q) => $q->whereIn('kode', $this->kodeSkema()))
            ->get()
            ->filter(fn (BeasiswaPenerima $p): bool => $p->mencakupTerm($term));

        $status = StatusMahasiswa::query()
            ->where('tahun_akademik_id', $term->id)
            ->whereIn('mahasiswa_id', $penerima->pluck('mahasiswa_id'))
            ->get()
            ->keyBy('mahasiswa_id');

        return $penerima
            ->sortBy(fn (BeasiswaPenerima $p): string => (string) $p->mahasiswa?->nim)
            ->map(function (BeasiswaPenerima $p) use ($status, $term): array {
                $s = $status->get($p->mahasiswa_id);

                return [
                    'nim' => $p->mahasiswa?->nim,
                    'nama' => $p->mahasiswa?->nama,
                    'prodi' => $p->mahasiswa?->prodi?->kode,
                    'skema' => $p->beasiswa?->kode,
                    'semester' => $term->kode,

                    // Kode hurufnya, bukan objek enumnya: baris ini berakhir di
                    // fputcsv, yang tidak dapat mengubah enum menjadi teks dan
                    // menggagalkan seluruh unduhan bila diberi satu.
                    'status' => $s?->status?->value,
                    'semester_ke' => $s?->semester_ke,
                    'sks_semester' => $s?->sks_semester,
                    'sks_kumulatif' => $s?->sks_kumulatif,
                    'ips' => $s?->ips,
                    'ipk' => $s?->ipk,

                    /*
                     * The two flags that make the row honest.
                     *
                     * Without them a missing student and a student with a
                     * genuine zero look identical, and provisional grades look
                     * like reported ones.
                     */
                    'ada_status' => $s !== null,
                    'final' => (bool) $s?->is_final,
                ];
            })
            ->values();
    }

    /**
     * Summary of what would be reported, for the screen.
     *
     * @return array{penerima: int, tanpa_status: int, belum_final: int}
     */
    public function ringkas(TahunAkademik $term): array
    {
        $baris = $this->baris($term);

        return [
            'penerima' => $baris->count(),
            'tanpa_status' => $baris->where('ada_status', false)->count(),
            'belum_final' => $baris->where('ada_status', true)->where('final', false)->count(),
        ];
    }

    public function csv(TahunAkademik $term): StreamedResponse
    {
        $baris = $this->baris($term);
        $pemisah = (string) config('bkd.ekspor.pemisah_csv');

        return response()->streamDownload(function () use ($baris, $pemisah): void {
            $keluaran = fopen('php://output', 'wb');
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, [
                'NIM', 'Nama', 'Prodi', 'Skema', 'Semester', 'Status', 'Semester Ke',
                'SKS Semester', 'SKS Kumulatif', 'IPS', 'IPK', 'Keterangan',
            ], $pemisah, '"', '\\');

            foreach ($baris as $satu) {
                fputcsv($keluaran, [
                    $satu['nim'],
                    $satu['nama'],
                    $satu['prodi'],
                    $satu['skema'],
                    $satu['semester'],
                    $satu['status'],
                    $satu['semester_ke'],
                    $satu['sks_semester'],
                    $satu['sks_kumulatif'],
                    $satu['ips'],
                    $satu['ipk'],
                    $this->keterangan($satu),
                ], $pemisah, '"', '\\');
            }

            fclose($keluaran);
        }, "kipk-{$term->kode}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Why a row cannot be taken at face value, in the row itself.
     *
     * In the file rather than in a covering note: whoever uploads this will not
     * have the note, and a blank cell beside a name reads as a fact about that
     * person.
     *
     * @param array<string, mixed> $baris
     */
    private function keterangan(array $baris): string
    {
        if (!$baris['ada_status']) {
            return 'TIDAK ADA STATUS SEMESTER INI — periksa keaktifannya sebelum dilaporkan';
        }

        return $baris['final'] ? '' : 'Nilai belum final';
    }

    /** @return array<int, string> */
    private function kodeSkema(): array
    {
        return array_values(array_filter((array) config('kipk.beasiswa_kode', [])));
    }
}
