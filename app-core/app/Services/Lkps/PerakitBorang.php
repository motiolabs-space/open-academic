<?php

declare(strict_types=1);

namespace App\Services\Lkps;

use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;

/**
 * Arranges the canonical quantities into the tables a form asks for.
 *
 * This is the thin layer. IndikatorLkps does the counting once; everything
 * here is placement — which number goes in which row of which table. A second
 * LAM is a second block in config/lkps.php, not a second class.
 *
 * **Table numbers are not invented here.** They differ between LAMs and change
 * between revisions of an instrument, and a plausible-looking number is worse
 * than a blank one: somebody copies it into a real submission without checking.
 * Config ships them empty for the campus to fill from the form actually in
 * force; until then the tables assemble and print without a number.
 *
 * Tables this application cannot fill are returned **with their reason**, never
 * as empty rows. A blank cell in an accreditation table is read as a zero, and
 * a zero in the research table is a claim about the programme.
 */
class PerakitBorang
{
    public function __construct(private readonly IndikatorLkps $indikator) {}

    /**
     * Every table, for one programme, as of one term.
     *
     * @return array<int, array{kunci: string, nomor: string|null, judul: string, terisi: bool, alasan: string|null, kolom: array<int, string>, baris: array<int, array<int, mixed>>, catatan: string|null}>
     */
    public function rakit(Prodi $prodi, TahunAkademik $term): array
    {
        $tahunAkhir = $term->tahun_mulai;
        $deret = max(1, (int) config('lkps.tahun_deret', 3));
        $tahun = range($tahunAkhir - $deret + 1, $tahunAkhir);

        return [
            $this->seleksi($prodi, $tahun),
            $this->mahasiswaDosen($prodi, $term),
            $this->lulusan($prodi, $tahun),
            $this->putusStudi($prodi, $term),
            ...$this->tidakTerisi(),
        ];
    }

    /* ---------------------------------------------------------------------
     | Tabel yang terisi
     |-------------------------------------------------------------------- */

    /** @param array<int, int> $tahun */
    private function seleksi(Prodi $prodi, array $tahun): array
    {
        $baris = [];

        foreach ($tahun as $satu) {
            $angka = $this->indikator->keketatan($prodi, $satu);

            $baris[] = [
                $satu,
                $angka['pendaftar'],
                $angka['diterima'],
                $angka['daftar_ulang'],

                // Em dash, not "1,00". A ratio with no denominator has no
                // value, and printing one invents a fact.
                $this->rasio($angka['keketatan']),
            ];
        }

        return $this->tabel('seleksi', [
            'Tahun', 'Pendaftar', 'Diterima', 'Mendaftar Ulang', 'Keketatan',
        ], $baris);
    }

    private function mahasiswaDosen(Prodi $prodi, TahunAkademik $term): array
    {
        $rasio = $this->indikator->rasioDosenMahasiswa($prodi, $term);

        return $this->tabel('mahasiswa_dosen', [
            'Semester', 'Mahasiswa Aktif', 'Dosen Tetap (DTPS)', 'Rasio',
        ], [[
            $term->nama,
            $this->indikator->mahasiswaAktif($prodi, $term),
            $this->indikator->dtps($prodi),
            $this->rasio($rasio),
        ]], $rasio === null
            ? 'Rasio tidak dapat dihitung: belum ada dosen tetap tercatat pada prodi ini.'
            : null);
    }

    /** @param array<int, int> $tahun */
    private function lulusan(Prodi $prodi, array $tahun): array
    {
        $baris = [];
        $catatan = [];

        foreach ($tahun as $satu) {
            $angka = $this->indikator->lulusan($prodi, $satu);

            $baris[] = [
                $satu,
                $angka['jumlah'],
                $this->angka($angka['ipk_min']),
                $this->angka($angka['ipk_rata']),
                $this->angka($angka['ipk_maks']),
                $this->angka($angka['masa_studi_rata']),
                $angka['tepat_waktu'],
                $angka['dikecualikan'],
            ];

            if ($angka['catatan'] !== null) {
                $catatan[$angka['catatan']] = true;
            }
        }

        return $this->tabel('lulusan', [
            'Tahun', 'Lulusan', 'IPK Min', 'IPK Rata-rata', 'IPK Maks',
            'Masa Studi (smt)', 'Tepat Waktu', 'Dikecualikan',
        ], $baris, $catatan === [] ? null : implode(' ', array_keys($catatan)));
    }

    private function putusStudi(Prodi $prodi, TahunAkademik $term): array
    {
        return $this->tabel('putus_studi', [
            'Semester', 'Putus Studi',
        ], [[
            $term->nama,
            $this->indikator->putusStudi($prodi, $term),
        ]], 'Ambang non-aktif berturut-turut belum diterapkan — lihat docs/LKPS-DEFINISI.md butir 7.');
    }

    /* ---------------------------------------------------------------------
     | Tabel yang tidak terisi
     |-------------------------------------------------------------------- */

    /**
     * Groups this application does not hold, each carrying its reason.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tidakTerisi(): array
    {
        $judul = [
            'tracer_study' => 'Tracer Study & Keterserapan Lulusan',
            'penelitian_pkm' => 'Penelitian, PkM & Publikasi DTPS',
            'kepuasan_layanan' => 'Kepuasan Mahasiswa atas Layanan',
        ];

        $hasil = [];

        foreach ($this->indikator->tidakTersedia() as $kunci => $alasan) {
            $hasil[] = [
                'kunci' => $kunci,
                'nomor' => config("lkps.borang.{$kunci}.nomor"),
                'judul' => $judul[$kunci] ?? $kunci,
                'terisi' => false,
                'alasan' => $alasan,
                'kolom' => [],
                'baris' => [],
                'catatan' => null,
            ];
        }

        return $hasil;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /**
     * @param array<int, string> $kolom
     * @param array<int, array<int, mixed>> $baris
     * @return array<string, mixed>
     */
    private function tabel(string $kunci, array $kolom, array $baris, ?string $catatan = null): array
    {
        return [
            'kunci' => $kunci,
            'nomor' => config("lkps.borang.{$kunci}.nomor"),
            'judul' => (string) config("lkps.borang.{$kunci}.judul", $kunci),
            'terisi' => true,
            'alasan' => null,
            'kolom' => $kolom,
            'baris' => $baris,
            'catatan' => $catatan,
        ];
    }

    /**
     * A ratio, in the same decimal notation as every other number here.
     *
     * Without this it printed "1 : 3.6" beside "3,29" — two decimal marks in
     * one table, on a form whose numbers get read side by side.
     */
    private function rasio(?float $nilai): string
    {
        return $nilai === null ? '—' : '1 : '.$this->angka($nilai);
    }

    /** Null stays visibly absent instead of becoming a zero. */
    private function angka(?float $nilai): string
    {
        return $nilai === null ? '—' : number_format($nilai, 2, ',', '.');
    }
}
