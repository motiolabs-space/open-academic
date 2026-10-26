<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\DTOs\Pmb\HasilImporNilai;
use App\DTOs\Pmb\JadwalSeleksiData;
use App\Enums\ApplicantStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbJadwalSeleksi;
use App\Models\Pmb\PmbPendaftar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Selection: when it happens, and what came out of it.
 *
 * The test itself is not run here (ROADMAP §Kenapa CBT dan LMS tidak akan
 * masuk). A campus runs it on whatever CBT it has and brings the scores back
 * as a CSV with two columns any CBT can export: the registration number this
 * system printed on the exam card, and a score.
 *
 * The import follows the rule the Feeder sync does: **every row is checked
 * before any row is written**, and a file with one bad row writes nothing and
 * reports every bad row at once. A half-imported score sheet is worse than
 * none — the committee cannot tell which half it is looking at.
 */
class SeleksiPmbService
{
    public function __construct(private readonly PmbService $pmb) {}

    public function tambahJadwal(PmbGelombang $gelombang, JadwalSeleksiData $data): PmbJadwalSeleksi
    {
        return PmbJadwalSeleksi::create([
            'pmb_gelombang_id' => $gelombang->id,
            'nama' => $data->nama,
            'mulai' => $data->mulai,
            'selesai' => $data->selesai,
            'lokasi' => $data->lokasi,
            'keterangan' => $data->keterangan,
        ]);
    }

    public function hapusJadwal(PmbJadwalSeleksi $jadwal): void
    {
        $jadwal->delete();
    }

    /**
     * Imports a score sheet for one wave.
     *
     * Accepts comma- or semicolon-separated files (spreadsheet software on an
     * Indonesian locale writes the latter), a byte-order mark, and decimal
     * commas. Column order is free; extra columns are ignored.
     */
    public function imporNilai(PmbGelombang $gelombang, string $isi): HasilImporNilai
    {
        $baris = $this->bacaCsv($isi);

        if (is_string($baris)) {
            return new HasilImporNilai(0, [$baris]);
        }

        $pendaftar = PmbPendaftar::query()
            ->where('pmb_gelombang_id', $gelombang->id)
            ->whereIn('nomor_pendaftaran', array_column($baris, 'nomor'))
            ->get()
            ->keyBy('nomor_pendaftaran');

        [$nilai, $galat] = $this->periksa($baris, $pendaftar);

        if ($galat !== []) {
            return new HasilImporNilai(0, $galat);
        }

        DB::transaction(function () use ($nilai, $pendaftar, $gelombang): void {
            foreach ($nilai as $nomor => $angka) {
                $pendaftar[$nomor]->update(['nilai_seleksi' => $angka]);
            }

            $gelombang->recordActivity('scores_imported', count($nilai).' nilai seleksi diimpor.');
        });

        if (!config('pmb.kelulusan_otomatis')) {
            return new HasilImporNilai(count($nilai));
        }

        [$lulus, $tidak] = $this->terapkanAmbang($gelombang);

        return new HasilImporNilai(count($nilai), [], $lulus, $tidak);
    }

    /**
     * Automatic acceptance by per-programme threshold. Off unless
     * PMB_KELULUSAN_OTOMATIS is set.
     *
     * Highest score first, so when seats run out it is the lower scores that
     * miss. Each applicant is tried on their first choice, then their second.
     * Somebody below both thresholds is rejected; somebody whose choices have
     * no threshold, or who missed only for want of seats on a programme with
     * no threshold to fall back on, is left for the committee — the rule
     * decides only where the campus wrote one down.
     *
     * Decisions go through PmbService like a person's would, so seats,
     * logging and announcement behave the same.
     *
     * @return array{0: int, 1: int} [accepted, rejected]
     */
    public function terapkanAmbang(PmbGelombang $gelombang): array
    {
        $gelombang->load('prodiDitawarkan.prodi');
        $tawaran = $gelombang->prodiDitawarkan->keyBy('prodi_id');

        $antrean = PmbPendaftar::query()
            ->with('akun')
            ->where('pmb_gelombang_id', $gelombang->id)
            ->where('status', ApplicantStatus::Seleksi->value)
            ->whereNotNull('nilai_seleksi')
            ->orderByDesc('nilai_seleksi')
            ->orderBy('id')
            ->get();

        $lulus = 0;
        $tidak = 0;

        foreach ($antrean as $pendaftar) {
            $nilai = (float) $pendaftar->nilai_seleksi;
            $adaAmbang = false;
            $diterima = false;

            foreach ([$pendaftar->prodi_pilihan_1_id, $pendaftar->prodi_pilihan_2_id] as $prodiId) {
                $t = $prodiId === null ? null : $tawaran->get($prodiId);

                if ($t?->ambang_lulus === null) {
                    continue;
                }

                $adaAmbang = true;

                if ($nilai < (float) $t->ambang_lulus) {
                    continue;
                }

                try {
                    $this->pmb->luluskan($pendaftar, $t->prodi);
                    $diterima = true;
                    $lulus++;

                    break;
                } catch (AturanAkademikException) {
                    // Seats full; try the next choice.
                }
            }

            if (!$diterima && $adaAmbang && $this->dibawahSemuaAmbang($pendaftar, $tawaran, $nilai)) {
                $this->pmb->tidakLuluskan($pendaftar);
                $tidak++;
            }
        }

        return [$lulus, $tidak];
    }

    /**
     * Below the threshold on every choice that has one, and every choice has
     * one. A choice with no threshold is a programme that decides by hand.
     */
    private function dibawahSemuaAmbang(PmbPendaftar $pendaftar, Collection $tawaran, float $nilai): bool
    {
        foreach (array_filter([$pendaftar->prodi_pilihan_1_id, $pendaftar->prodi_pilihan_2_id]) as $prodiId) {
            $ambang = $tawaran->get($prodiId)?->ambang_lulus;

            if ($ambang === null || $nilai >= (float) $ambang) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array{baris: int, nomor: string, nilai: string}>|string
     *                                                                            the data rows, or the reason the file cannot be read at all
     */
    private function bacaCsv(string $isi): array|string
    {
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;
        $baris = preg_split('/\r\n|\r|\n/', trim($isi)) ?: [];

        if ($baris === [] || trim($baris[0]) === '') {
            return __('pmb.impor.kosong');
        }

        $pemisah = substr_count($baris[0], ';') > substr_count($baris[0], ',') ? ';' : ',';
        $judul = array_map(fn ($k): string => mb_strtolower(trim((string) $k)), str_getcsv($baris[0], $pemisah, '"', ''));

        $kolomNomor = array_search('nomor_pendaftaran', $judul, true);
        $kolomNilai = array_search('nilai', $judul, true);

        if ($kolomNomor === false || $kolomNilai === false) {
            return __('pmb.impor.tanpa_kolom');
        }

        $data = [];

        foreach (array_slice($baris, 1) as $i => $teks) {
            if (trim($teks) === '') {
                continue;
            }

            $sel = str_getcsv($teks, $pemisah, '"', '');

            $data[] = [
                // +2: one for the header, one because people count from 1.
                'baris' => $i + 2,
                'nomor' => trim((string) ($sel[$kolomNomor] ?? '')),
                'nilai' => trim((string) ($sel[$kolomNilai] ?? '')),
            ];
        }

        if ($data === []) {
            return __('pmb.impor.kosong');
        }

        $maks = (int) config('pmb.impor.maks_baris');

        if (count($data) > $maks) {
            return __('pmb.impor.terlalu_banyak', ['maks' => $maks]);
        }

        return $data;
    }

    /**
     * @param array<int, array{baris: int, nomor: string, nilai: string}> $baris
     * @param Collection<string, PmbPendaftar> $pendaftar
     * @return array{0: array<string, float>, 1: array<int, string>}
     */
    private function periksa(array $baris, Collection $pendaftar): array
    {
        $maks = (int) config('pmb.impor.nilai_maks');
        $nilai = [];
        $galat = [];
        $terlihat = [];

        foreach ($baris as $b) {
            if (isset($terlihat[$b['nomor']])) {
                $galat[] = __('pmb.impor.ganda', $b);

                continue;
            }

            $terlihat[$b['nomor']] = true;
            $orang = $pendaftar->get($b['nomor']);

            if ($orang === null) {
                $galat[] = __('pmb.impor.tidak_dikenal', $b);

                continue;
            }

            if ($orang->status !== ApplicantStatus::Seleksi) {
                $galat[] = __('pmb.impor.status', [...$b, 'status' => $orang->status->label()]);

                continue;
            }

            $teks = str_contains($b['nilai'], '.') ? $b['nilai'] : str_replace(',', '.', $b['nilai']);

            if (!is_numeric($teks) || (float) $teks < 0 || (float) $teks > $maks) {
                $galat[] = __('pmb.impor.nilai', [...$b, 'maks' => $maks]);

                continue;
            }

            $nilai[$b['nomor']] = round((float) $teks, 2);
        }

        return [$nilai, $galat];
    }
}
