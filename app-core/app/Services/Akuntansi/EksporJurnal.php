<?php

declare(strict_types=1);

namespace App\Services\Akuntansi;

use App\Enums\JenisDokumenAkuntansi;
use App\Models\Akuntansi\DokumenAkuntansi;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The journal sheet, as a file.
 *
 * Not a fallback for the API so much as the thing that still works when the API
 * does not: an expired key, an unreachable host, a campus that never connected
 * one. Financial reporting cannot be blocked on an integration being healthy.
 *
 * One row per journal line, not per document, so the file can be summed and
 * checked by hand — debit total against credit total is the first thing anybody
 * receiving it will do.
 */
class EksporJurnal
{
    public function csv(?DateTimeInterface $dari = null, ?DateTimeInterface $sampai = null): StreamedResponse
    {
        $dokumen = DokumenAkuntansi::query()
            ->whereIn('jenis', [JenisDokumenAkuntansi::Invoice->value, JenisDokumenAkuntansi::Jurnal->value])

            // whereDate, not where: created_at is a timestamp, and comparing it
            // against a bare date silently drops everything booked after
            // midnight on the closing day.
            ->when($dari !== null, fn ($q) => $q->whereDate('created_at', '>=', $dari))
            ->when($sampai !== null, fn ($q) => $q->whereDate('created_at', '<=', $sampai))
            ->orderBy('id')
            ->get();

        $nama = sprintf(
            'jurnal-%s-%s.csv',
            $dari?->format('Ymd') ?? 'awal',
            $sampai?->format('Ymd') ?? now()->format('Ymd'),
        );

        return response()->streamDownload(function () use ($dokumen): void {
            $keluaran = fopen('php://output', 'wb');

            if ((bool) config('akuntansi.ekspor.bom_utf8')) {
                fwrite($keluaran, "\xEF\xBB\xBF");
            }

            fputcsv($keluaran, [
                'Tanggal', 'Nomor Referensi', 'Keterangan', 'Kode Akun',
                'Debit', 'Kredit', 'Status Kirim', 'ID Easy Accounting',
            ], ',', '"', '\\');

            foreach ($dokumen as $satu) {
                foreach ($this->baris($satu) as $baris) {
                    fputcsv($keluaran, $baris, ',', '"', '\\');
                }
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * One document expanded into its journal lines.
     *
     * An invoice has no `entries` of its own — easyERP derives Dr Piutang /
     * Cr Pendapatan from its chart of accounts. This export cannot see that
     * chart, so it writes the same two lines using the codes configured here,
     * and labels them as such. The alternative is exporting invoices as a
     * single unbalanced row, which is not a journal.
     *
     * @return array<int, array<int, string|int>>
     */
    private function baris(DokumenAkuntansi $dokumen): array
    {
        $payload = $dokumen->payload;
        $akun = config('akuntansi.akun');

        $umum = [
            $payload['transaction_date'] ?? $dokumen->created_at?->toDateString() ?? '',
            $payload['reference_number'] ?? $dokumen->kunci_idempotensi,
            $payload['description'] ?? $dokumen->jenis->label(),
        ];

        $ekor = [
            $dokumen->status->label(),
            $dokumen->easyerp_id ?? '',
        ];

        if ($dokumen->jenis === JenisDokumenAkuntansi::Invoice) {
            $nominal = (int) $dokumen->nominal;

            return [
                [...$umum, $akun['piutang'], $nominal, 0, ...$ekor],
                [...$umum, $akun['pendapatan'], 0, $nominal, ...$ekor],
            ];
        }

        return collect($payload['entries'] ?? [])
            ->map(fn (array $e): array => [
                ...$umum,
                $e['account_code'] ?? '',
                (int) ($e['debit'] ?? 0),
                (int) ($e['credit'] ?? 0),
                ...$ekor,
            ])
            ->all();
    }
}
