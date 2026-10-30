<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy drafting as an assignment type (IKU 8, ADR-041 §1).
 *
 * The rest of the fact already fits the row: the title is the policy, the
 * partner is the body that issued the decree or invitation, the start date is
 * when it happened, and the document is the evidence. What the row could not
 * say is the form of involvement, whose policy it was, and which official
 * document names the lecturer — the three things the decree checks.
 *
 * Nullable: every other assignment type leaves them empty, and the form
 * request requires them only for `penyusunan_kebijakan`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasan_dosen', function (Blueprint $table) {
            $table->string('kebijakan_bentuk', 24)->nullable()->after('luaran_tahun'); // BentukKeterlibatanKebijakan
            $table->string('kebijakan_tingkat', 24)->nullable()->after('kebijakan_bentuk'); // TingkatKebijakan
            $table->string('kebijakan_bukti_jenis', 32)->nullable()->after('kebijakan_tingkat'); // JenisBuktiKebijakan
            $table->string('kebijakan_nomor_bukti', 128)->nullable()->after('kebijakan_bukti_jenis');
        });
    }

    public function down(): void
    {
        Schema::table('penugasan_dosen', function (Blueprint $table) {
            $table->dropColumn(['kebijakan_bentuk', 'kebijakan_tingkat', 'kebijakan_bukti_jenis', 'kebijakan_nomor_bukti']);
        });
    }
};
