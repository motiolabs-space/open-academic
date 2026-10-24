<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Late fees.
 *
 * The fee itself is an invoice line (`tagihan_item.jenis = denda`), so
 * `tagihan.total` stays the sum of its lines. Three columns on the invoice
 * carry what the lines alone cannot answer cheaply:
 *
 *  - `denda`: the fee currently included in `total`, net of any waiver. The
 *    KRS payment gate subtracts it before comparing against the threshold,
 *    and it must do so without a query per student.
 *  - `denda_dihitung_sampai`: the day accrual last ran for this invoice. The
 *    daily job charges only periods begun since, which is what makes a
 *    second run on the same day a no-op.
 *  - `denda_dibebaskan_at`: set when the finance office forgave the fee.
 *    Accrual stops for good; the reason and the signatory live on the
 *    waiver line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->unsignedBigInteger('denda')->default(0)->after('terbayar');
            $table->date('denda_dihitung_sampai')->nullable()->after('dispensasi_catatan');
            $table->timestamp('denda_dibebaskan_at')->nullable()->after('denda_dihitung_sampai');

            // The nightly scan: unsettled invoices past their due date.
            $table->index(['status', 'jatuh_tempo'], 'tagihan_status_jatuh_tempo_index');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropIndex('tagihan_status_jatuh_tempo_index');
            $table->dropColumn(['denda', 'denda_dihitung_sampai', 'denda_dibebaskan_at']);
        });
    }
};
