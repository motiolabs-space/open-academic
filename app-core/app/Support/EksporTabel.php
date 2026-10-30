<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One table, downloaded as CSV or XLSX — the two forms the ministry systems'
 * bulk-entry screens take.
 *
 * CSV goes through Csv, so a name typed by a student that starts with `=`
 * reaches the spreadsheet as text (PR #30). XLSX goes through LembarKerjaXlsx,
 * whose text cells are inline strings: a spreadsheet never evaluates those, so
 * they are written exactly as stored, without the apostrophe the CSV needs.
 */
final class EksporTabel
{
    public const FORMAT = ['csv', 'xlsx'];

    /**
     * @param array<int, string> $judul
     * @param iterable<int, array<int, string|int|float|null>> $baris
     */
    public static function unduh(string $namaBerkas, string $format, array $judul, iterable $baris): StreamedResponse|BinaryFileResponse
    {
        return match ($format) {
            'csv' => self::csv($namaBerkas.'.csv', $judul, $baris),
            'xlsx' => self::xlsx($namaBerkas.'.xlsx', $judul, $baris),
            default => throw new InvalidArgumentException("Format ekspor tidak dikenal: {$format}"),
        };
    }

    /**
     * @param array<int, string> $judul
     * @param iterable<int, array<int, string|int|float|null>> $baris
     */
    private static function csv(string $nama, array $judul, iterable $baris): StreamedResponse
    {
        $pemisah = (string) config('bkd.ekspor.pemisah_csv', ',');

        return response()->streamDownload(function () use ($judul, $baris, $pemisah): void {
            $keluaran = fopen('php://output', 'wb');
            fwrite($keluaran, "\xEF\xBB\xBF");

            Csv::tulis($keluaran, $judul, $pemisah);

            foreach ($baris as $satu) {
                Csv::tulis($keluaran, $satu, $pemisah);
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param array<int, string> $judul
     * @param iterable<int, array<int, string|int|float|null>> $baris
     */
    private static function xlsx(string $nama, array $judul, iterable $baris): BinaryFileResponse
    {
        $lembar = new LembarKerjaXlsx('Data', $judul);

        foreach ($baris as $satu) {
            $lembar->tambah($satu);
        }

        $path = tempnam(sys_get_temp_dir(), 'oa-ekspor-');
        $path = $lembar->simpan($path === false ? sys_get_temp_dir().'/oa-ekspor-'.uniqid().'.xlsx' : $path);

        return response()
            ->download($path, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }
}
