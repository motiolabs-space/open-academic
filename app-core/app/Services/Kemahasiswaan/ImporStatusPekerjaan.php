<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\BarisImporStatus;
use App\DTOs\Kemahasiswaan\HasilImporStatus;
use App\Enums\StatusPekerjaanAlumni;
use App\Enums\SumberStatusPekerjaan;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Kemahasiswaan\Mahasiswa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Graduates' employment status, read back from a tracer-study file into
 * `alumni.status_pekerjaan` (ADR-037 §2).
 *
 * Two sources write such a file: the national tracer study's results, and
 * Open Campus, which runs the campus's own questionnaire. Either way the
 * answer is collected elsewhere; this only records the outcome on the
 * graduate's row, so `GET /iku-data` and `GET /graduates` have something to
 * count. Who owns the answer, and why it is stored here at all, is in the ADR.
 *
 * Checked before anything is written, all at once, in the same spirit as the
 * Feeder pre-send validator: every refused line is reported with its line
 * number and reason. Applying re-checks the file and writes only accepted
 * lines; refused ones never block the rest.
 *
 * Re-running a file is safe. A line identical to what is stored is counted as
 * unchanged, and a line older than what is stored (by survey date) is refused
 * — a late export of last year's tracer must not overwrite this year's answer.
 */
class ImporStatusPekerjaan
{
    public const MAKS_BARIS = 5000;

    /** Accepted header names, normalised, for each field. */
    private const ALIAS = [
        'nim' => ['nim', 'nomor_induk_mahasiswa'],
        'status' => ['status', 'status_pekerjaan', 'status_kerja'],
        'instansi' => ['instansi', 'nama_instansi', 'perusahaan', 'tempat_kerja'],
        'jabatan' => ['jabatan', 'pekerjaan', 'posisi'],
        'mulai_bekerja' => ['mulai_bekerja', 'tanggal_mulai_bekerja', 'tanggal_mulai_kerja'],
        'masa_tunggu_bulan' => ['masa_tunggu_bulan', 'masa_tunggu'],
        'tanggal_survei' => ['tanggal_survei', 'tanggal_pengisian', 'tanggal_isi'],
    ];

    /** Checks a file without writing anything. */
    public function periksa(string $isi, SumberStatusPekerjaan $sumber): HasilImporStatus
    {
        [$judul, $baris] = $this->baca($isi);

        $kolom = $this->petaKolom($judul);
        $kurang = array_diff(['nim', 'status'], array_keys($kolom));

        if ($kurang !== []) {
            return new HasilImporStatus($sumber, [], [__('lulusan.tolak.kolom_kurang', ['kolom' => implode(', ', $kurang)])]);
        }

        if ($baris === []) {
            return new HasilImporStatus($sumber, [], [__('lulusan.tolak.berkas_kosong')]);
        }

        if (count($baris) > self::MAKS_BARIS) {
            return new HasilImporStatus($sumber, [], [__('lulusan.tolak.terlalu_banyak', ['maks' => self::MAKS_BARIS])]);
        }

        $nimDiBerkas = array_values(array_filter(array_map(
            fn (array $sel): string => trim((string) ($sel[$kolom['nim']] ?? '')),
            $baris,
        )));

        // Two reads for the whole file, however long it is.
        $mahasiswa = Mahasiswa::query()->whereIn('nim', $nimDiBerkas)->pluck('id', 'nim');
        $alumni = Alumni::query()->whereIn('mahasiswa_id', $mahasiswa->values())->get()->keyBy('mahasiswa_id');

        $dilihat = [];
        $hasil = [];

        foreach ($baris as $i => $sel) {
            $nomor = $i + 2;
            $ambil = fn (string $f): string => isset($kolom[$f]) ? trim((string) ($sel[$kolom[$f]] ?? '')) : '';
            $nim = $ambil('nim');
            $alasan = [];

            $mahasiswaId = $mahasiswa[$nim] ?? null;
            $barisAlumni = $mahasiswaId === null ? null : $alumni->get($mahasiswaId);

            if ($nim === '') {
                $alasan[] = __('lulusan.tolak.nim_kosong');
            } elseif (isset($dilihat[$nim])) {
                $alasan[] = __('lulusan.tolak.nim_ganda', ['baris' => $dilihat[$nim]]);
            } elseif ($mahasiswaId === null) {
                $alasan[] = __('lulusan.tolak.nim_tidak_dikenal');
            } elseif ($barisAlumni === null) {
                $alasan[] = __('lulusan.tolak.bukan_lulusan');
            }

            if ($nim !== '' && !isset($dilihat[$nim])) {
                $dilihat[$nim] = $nomor;
            }

            $teksStatus = $ambil('status');
            $status = StatusPekerjaanAlumni::dariTeks($teksStatus);

            if ($status === null) {
                $alasan[] = __('lulusan.tolak.status_tidak_dikenal', ['status' => $teksStatus]);
            }

            $mulai = $this->tanggal($ambil('mulai_bekerja'), 'mulai_bekerja', $alasan);
            $survei = $this->tanggal($ambil('tanggal_survei'), 'tanggal_survei', $alasan) ?? CarbonImmutable::today();

            if ($survei->isFuture()) {
                $alasan[] = __('lulusan.tolak.tanggal_masa_depan');
            }

            $masaTunggu = null;
            $teksMasa = $ambil('masa_tunggu_bulan');

            if ($teksMasa !== '') {
                if (!ctype_digit($teksMasa) || (int) $teksMasa > 120) {
                    $alasan[] = __('lulusan.tolak.masa_tunggu_tidak_sah');
                } else {
                    $masaTunggu = (int) $teksMasa;
                }
            }

            $tersimpan = $barisAlumni?->status_pekerjaan_per;

            if ($tersimpan !== null && $survei->lt($tersimpan)) {
                $alasan[] = __('lulusan.tolak.lebih_lama', ['tanggal' => $tersimpan->toDateString()]);
            }

            $satu = new BarisImporStatus(
                baris: $nomor,
                nim: $nim,
                alumniId: $barisAlumni?->id,
                status: $status,
                instansi: $this->teks($ambil('instansi')),
                jabatan: $this->teks($ambil('jabatan')),
                mulaiBekerja: $mulai,
                masaTungguBulan: $masaTunggu,
                tanggalSurvei: $survei,
                alasan: $alasan,
            );

            $hasil[] = $alasan === [] && $barisAlumni !== null && $this->sama($barisAlumni, $satu, $sumber)
                ? new BarisImporStatus(...[...get_object_vars($satu), 'tidakBerubah' => true])
                : $satu;
        }

        return new HasilImporStatus($sumber, $hasil);
    }

    /**
     * Writes the accepted lines, after checking the file again.
     *
     * One transaction: a file is applied entirely or not at all, so a failure
     * halfway does not leave half a cohort on the new survey.
     */
    public function terapkan(string $isi, SumberStatusPekerjaan $sumber): HasilImporStatus
    {
        $hasil = $this->periksa($isi, $sumber);

        if (!$hasil->dapatDiterapkan()) {
            return $hasil;
        }

        $baris = $hasil->akanDiterapkan();

        DB::transaction(function () use ($baris, $sumber): void {
            $alumni = Alumni::query()->whereIn('id', array_map(fn (BarisImporStatus $b): int => (int) $b->alumniId, $baris))
                ->get()->keyBy('id');

            foreach ($baris as $b) {
                $alumni[$b->alumniId]->update([
                    'status_pekerjaan' => $b->status,
                    'pekerjaan' => $b->jabatan,
                    'instansi' => $b->instansi,
                    'mulai_bekerja' => $b->mulaiBekerja?->toDateString(),
                    'masa_tunggu_bulan' => $b->masaTungguBulan,
                    'status_pekerjaan_sumber' => $sumber,
                    'status_pekerjaan_per' => $b->tanggalSurvei?->toDateString(),
                    'status_pekerjaan_diimpor_at' => now(),
                ]);
            }
        });

        return new HasilImporStatus($sumber, $hasil->baris, [], count($baris));
    }

    /**
     * Header and rows of a CSV, whichever separator it uses.
     *
     * Spreadsheets in Indonesian locales save with a semicolon; the ministry's
     * downloads use a comma. The header line decides, and a UTF-8 BOM is
     * dropped.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function baca(string $isi): array
    {
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;
        $baris = preg_split('/\r\n|\n|\r/', $isi) ?: [];
        $baris = array_values(array_filter($baris, fn (string $b): bool => trim($b) !== ''));

        if ($baris === []) {
            return [[], []];
        }

        $kepala = $baris[0];
        $pemisah = collect([';', ',', "\t"])->sortByDesc(fn (string $p): int => substr_count($kepala, $p))->first();

        $urai = fn (string $b): array => str_getcsv($b, $pemisah, '"', '');

        return [$urai(array_shift($baris)), array_map($urai, $baris)];
    }

    /**
     * Field => column index, from whatever the header calls it.
     *
     * @param array<int, string> $judul
     * @return array<string, int>
     */
    private function petaKolom(array $judul): array
    {
        $normal = array_map(
            fn (string $j): string => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($j))) ?? '', '_'),
            $judul,
        );

        $peta = [];

        foreach (self::ALIAS as $medan => $nama) {
            foreach ($normal as $i => $n) {
                if (in_array($n, $nama, true)) {
                    $peta[$medan] = $i;
                    break;
                }
            }
        }

        return $peta;
    }

    /** @param array<int, string> $alasan */
    private function tanggal(string $teks, string $kolom, array &$alasan): ?CarbonImmutable
    {
        if ($teks === '') {
            return null;
        }

        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            try {
                $t = CarbonImmutable::createFromFormat($format, $teks);
            } catch (Throwable) {
                $t = false;
            }

            if ($t instanceof CarbonImmutable && $t->format(ltrim($format, '!')) === $teks) {
                return $t;
            }
        }

        $alasan[] = __('lulusan.tolak.tanggal_tidak_sah', ['kolom' => $kolom]);

        return null;
    }

    private function teks(string $nilai): ?string
    {
        return $nilai === '' ? null : mb_substr($nilai, 0, 255);
    }

    /** Whether a line says nothing the graduate's row does not already say. */
    private function sama(Alumni $a, BarisImporStatus $b, SumberStatusPekerjaan $sumber): bool
    {
        return $a->status_pekerjaan === $b->status
            && $a->status_pekerjaan_sumber === $sumber
            && $a->instansi === $b->instansi
            && $a->pekerjaan === $b->jabatan
            && $a->mulai_bekerja?->toDateString() === $b->mulaiBekerja?->toDateString()
            && $a->masa_tunggu_bulan === $b->masaTungguBulan
            && $a->status_pekerjaan_per?->toDateString() === $b->tanggalSurvei?->toDateString();
    }
}
