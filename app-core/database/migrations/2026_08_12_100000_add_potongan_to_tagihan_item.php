<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes it possible for a student to owe less.
 *
 * `tagihan_item.nominal` was unsigned, which meant a reduction could not be
 * stored at all — not "was not implemented", but *could not exist*. Waivers and
 * scholarships are the two ordinary ways an Indonesian campus lowers a bill, so
 * every campus using this was keeping them somewhere else and reconciling by
 * hand.
 *
 * A reduction is a line on the invoice with a negative amount, rather than a
 * separate table netted off at read time. Ten places in this application read
 * `tagihan.total` and `tagihan.terbayar` — the KRS payment gate, the graduation
 * checklist, the arrears reminder, the dashboards — and all of them rely on
 * `total` being what the student actually owes. Keeping `total = SUM(items)`
 * true means none of them change, and none of them can drift.
 *
 * The traceability columns are not optional bookkeeping. Lowering a bill is the
 * highest-value fraudulent action available in this system, and a reduction
 * without a name and a reason attached is indistinguishable from one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_item', function (Blueprint $table) {
            // The change that was blocking the whole module.
            $table->bigInteger('nominal')->change();

            $table->string('jenis', 16)->default('tagihan')->after('tarif_id'); // JenisItemTagihan

            /*
             * Where the reduction came from.
             *
             * Null on a discretionary waiver, which is a decision about one
             * student rather than an entitlement under a scheme. The reason
             * column carries the justification in that case.
             */
            $table->foreignId('beasiswa_penerima_id')->nullable()->after('jenis')
                ->constrained('beasiswa_penerima')->nullOnDelete();

            $table->string('alasan', 500)->nullable()->after('nominal');

            $table->foreignId('diputus_by_staff_id')->nullable()->after('alasan')
                ->constrained('staff')->nullOnDelete();

            $table->timestamp('diputus_at')->nullable()->after('diputus_by_staff_id');

            $table->index(['tagihan_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_item', function (Blueprint $table) {
            $table->dropForeign(['beasiswa_penerima_id']);
            $table->dropForeign(['diputus_by_staff_id']);
            $table->dropIndex(['tagihan_id', 'jenis']);
            $table->dropColumn([
                'jenis', 'beasiswa_penerima_id', 'alasan', 'diputus_by_staff_id', 'diputus_at',
            ]);
            $table->unsignedBigInteger('nominal')->change();
        });
    }
};
