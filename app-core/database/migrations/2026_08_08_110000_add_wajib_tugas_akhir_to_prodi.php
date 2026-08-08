<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a programme requires a final project before it will graduate anyone.
 *
 * Per programme rather than one global switch, because the answer genuinely
 * differs inside a single campus: most undergraduate programmes require one,
 * some vocational and professional programmes replace it with a competency
 * examination or a supervised placement.
 *
 * Defaults to true. A campus that turns it off for a programme has made a
 * decision; a campus that never had the requirement enforced has merely never
 * noticed it was missing — and shipping the permissive default is how the
 * second one happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->boolean('wajib_tugas_akhir')->default(true)->after('sks_lulus');
        });
    }

    public function down(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->dropColumn('wajib_tugas_akhir');
        });
    }
};
