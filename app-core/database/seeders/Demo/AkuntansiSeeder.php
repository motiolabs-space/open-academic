<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\PaymentStatus;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Services\Akuntansi\PengirimAkuntansi;
use App\Services\Akuntansi\PenjurnalanService;
use App\Support\Akuntansi;
use Illuminate\Database\Seeder;

/**
 * An accounting queue caught mid-flight.
 *
 * The demo's invoices are written straight to the table by KeuanganSeeder
 * rather than through the issuing service, so nothing would ever reach the
 * outbox on its own. This walks them afterwards.
 *
 * One batch is then sent and the rest left queued, because a monitor screen
 * showing everything green demonstrates none of what it is for: the queue
 * depth, the rupiah value not yet in the ledger, and the retry button.
 */
class AkuntansiSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * The module ships switched off, so the demo turns it on for itself.
         *
         * Left on afterwards rather than restored: a demo campus with an empty
         * accounting screen demonstrates nothing. An installation whose .env
         * says "nonaktif" still gets this data, and the screen's banner tells
         * the operator exactly which line to change to see it live.
         */
        config(['akuntansi.driver' => Akuntansi::PALSU]);

        $penjurnalan = app(PenjurnalanService::class);

        Tagihan::with(['mahasiswa', 'item'])
            ->orderBy('id')
            ->each(fn (Tagihan $tagihan) => $penjurnalan->tagihanTerbit($tagihan));

        Pembayaran::with('tagihan')
            ->where('status', PaymentStatus::Settlement->value)
            ->orderBy('id')
            ->each(fn (Pembayaran $pembayaran) => $penjurnalan->pembayaranDiterima($pembayaran));

        // Deliberately partial. The driver here is "palsu", so nothing leaves
        // the machine — but the screen shows a real mix of sent and waiting.
        app(PengirimAkuntansi::class)->jalankan(batas: 40);
    }
}
