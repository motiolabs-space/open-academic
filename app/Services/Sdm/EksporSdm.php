<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Services\Branding\BrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Getting the data out, while there is nothing to send it to.
 *
 * With no SISTER credentials, export is the whole of this module's usefulness
 * today — and it is not a placeholder for the integration so much as the part
 * that keeps working when the integration is down, the endpoint changes, or a
 * faculty office wants the numbers in a spreadsheet regardless.
 *
 * Formats are deliberately dumb: flat CSV and flat JSON. Anything cleverer would
 * make a consumer learn Open Academic's model before they could paste a column
 * into a ministry form.
 */
class EksporSdm
{
    public function __construct(
        private readonly PortofolioDosen $portofolio,
        private readonly BrandingService $brand,
    ) {}

    /** The signable workload sheet. */
    public function lembarBkd(BkdLaporan $laporan): DomPdf
    {
        $laporan->loadMissing(['dosen.prodi', 'tahunAkademik', 'baris', 'asesor1', 'asesor2', 'pengesah']);

        return Pdf::loadView('pdf.bkd', [
            'laporan' => $laporan,
            'baris' => $laporan->baris->groupBy(fn ($b): string => $b->unsur->value),
            'batas' => config('bkd.batas'),
            'institusi' => $this->brand->institutionName(),
            'kodeInstitusi' => $this->brand->institutionCode(),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Campus-wide BKD recap.
     *
     * Streamed rather than built in memory: a campus with two thousand lecturers
     * produces a file large enough that building it as a string is how a report
     * request takes down the web worker.
     */
    public function rekapBkdCsv(TahunAkademik $term): StreamedResponse
    {
        $baris = $this->portofolio->rekap($term);

        return $this->csv(
            "bkd-{$term->kode}.csv",
            ['NIDN', 'Nama', 'Program Studi', 'Semester', 'Status',
                'SKS Pendidikan', 'SKS Penelitian', 'SKS Pengabdian', 'SKS Penunjang',
                'SKS Total', 'Kesimpulan', 'Catatan Asesor'],
            collect($baris)->map(fn (array $b): array => array_values($b)),
        );
    }

    /**
     * Every recorded activity for a term, one row each.
     *
     * The sheet a research office asks for, and the one that maps most directly
     * onto a SISTER bulk-entry form.
     */
    public function kegiatanCsv(TahunAkademik $term): StreamedResponse
    {
        $baris = Dosen::aktif()
            ->orderBy('nama')
            ->get()
            ->flatMap(fn (Dosen $d): array => collect($this->portofolio->kegiatan($d, $term))
                ->map(fn (array $k): array => [
                    $d->nidn,
                    $d->namaLengkap(),
                    $term->kode,
                    $k['unsur'] ?? '',
                    $k['jenis'],
                    $k['judul'],
                    $k['peran'] ?? '',
                    $k['tingkat'] ?? '',
                    $k['mitra_nama'] ?? '',
                    $k['tanggal_mulai'],
                    $k['tanggal_selesai'] ?? '',
                    $k['sks_ekuivalen'] ?? '',
                    $k['luaran_jenis'] ?? '',
                    $k['luaran_identitas'] ?? '',
                    $k['luaran_tahun'] ?? '',
                    $k['terverifikasi'] ? 'ya' : 'belum',
                ])
                ->all());

        return $this->csv(
            "kegiatan-dosen-{$term->kode}.csv",
            ['NIDN', 'Nama', 'Semester', 'Unsur', 'Jenis', 'Judul', 'Peran', 'Tingkat',
                'Mitra', 'Mulai', 'Selesai', 'SKS Ekuivalen', 'Jenis Luaran',
                'Identitas Luaran', 'Tahun Luaran', 'Terverifikasi'],
            $baris,
        );
    }

    /**
     * One lecturer's whole record as JSON.
     *
     * The shape an integration script will consume. Kept per-lecturer rather
     * than campus-wide because that is the granularity SISTER works at, and a
     * failed submission should be retryable for one person rather than for
     * everybody.
     *
     * @return array<string, mixed>
     */
    public function portofolioJson(Dosen $dosen, ?TahunAkademik $term = null): array
    {
        $dosen->loadMissing(['prodi', 'riwayatPendidikan', 'riwayatJabatan', 'sertifikasi']);

        return [
            'dihasilkan_pada' => now()->toIso8601String(),
            'sumber' => config('app.name'),

            // Named so a consumer can tell a change of shape from a change of
            // data. Bumped when a field is removed or repurposed, not when one
            // is added.
            'versi' => '1',

            'dosen' => $this->portofolio->identitas($dosen),
            'semester' => $term === null ? null : $this->portofolio->semester($dosen, $term),
        ];
    }

    /**
     * @param array<int, string> $header
     * @param Collection<int, array<int, mixed>> $baris
     */
    private function csv(string $namaBerkas, array $header, Collection $baris): StreamedResponse
    {
        $pemisah = (string) config('bkd.ekspor.pemisah_csv');
        $bom = (bool) config('bkd.ekspor.bom_utf8');

        return response()->streamDownload(function () use ($header, $baris, $pemisah, $bom): void {
            $keluaran = fopen('php://output', 'wb');

            // Excel on an Indonesian Windows install reads a BOM-less UTF-8 CSV
            // as mojibake. The BOM is ugly; every degree-laden name rendering as
            // rubbish on the screen of the person checking it is worse.
            if ($bom) {
                fwrite($keluaran, "\xEF\xBB\xBF");
            }

            fputcsv($keluaran, $header, $pemisah, '"', '\\');

            foreach ($baris as $satu) {
                fputcsv($keluaran, $satu, $pemisah, '"', '\\');
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
