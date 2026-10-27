<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charges that follow from the study plan: per credit, and per named course.
 *
 * The fee matrix gains one dimension — the course a `mata_kuliah` row prices —
 * and invoice lines gain the provenance a KRS-derived charge needs to be
 * adjusted later without rewriting it: which course and plan row it billed,
 * and, for a correction, which earlier line it corrects. See ADR-030.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarif', function (Blueprint $table) {
            // Required for komponen=mata_kuliah, null for everything else.
            // Not a specificity dimension: it says *what* is priced, not *who*.
            $table->foreignId('mata_kuliah_id')->nullable()->after('golongan_ukt')
                ->constrained('mata_kuliah')->cascadeOnDelete();
        });

        Schema::table('tagihan_item', function (Blueprint $table) {
            // KomponenTarif of a KRS-derived line; null on fixed lines, which
            // carry their tariff row and never need to be matched again.
            $table->string('komponen', 32)->nullable()->after('jenis');

            // The course a KRS-derived line bills. The matching key: a student
            // who swaps class A for class B of the same course owes nothing more.
            $table->foreignId('mata_kuliah_id')->nullable()->after('komponen')
                ->constrained('mata_kuliah')->nullOnDelete();

            // The plan row that caused it. Plan rows are soft-deleted, so this
            // keeps pointing at a dropped class — and its deleted_at is the drop date.
            $table->foreignId('krs_detail_id')->nullable()->after('mata_kuliah_id')
                ->constrained('krs_detail')->nullOnDelete();

            // A correction names the line it corrects; the original stays.
            $table->foreignId('koreksi_atas_item_id')->nullable()->after('krs_detail_id')
                ->constrained('tagihan_item')->nullOnDelete();

            $table->index(['tagihan_id', 'mata_kuliah_id'], 'tagihan_item_tagihan_mk_index');
        });

        Schema::table('tagihan', function (Blueprint $table) {
            // Net KRS-derived charges inside `total`, kept by TagihanKrsService
            // so the KRS gate can set them aside without a query when zero.
            $table->unsignedBigInteger('nominal_krs')->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropColumn('nominal_krs');
        });

        Schema::table('tagihan_item', function (Blueprint $table) {
            $table->dropIndex('tagihan_item_tagihan_mk_index');
            $table->dropConstrainedForeignId('koreksi_atas_item_id');
            $table->dropConstrainedForeignId('krs_detail_id');
            $table->dropConstrainedForeignId('mata_kuliah_id');
            $table->dropColumn('komponen');
        });

        Schema::table('tarif', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mata_kuliah_id');
        });
    }
};
