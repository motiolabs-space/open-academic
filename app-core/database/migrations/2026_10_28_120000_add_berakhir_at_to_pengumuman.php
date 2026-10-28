<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An end to an announcement's display. Null keeps the old behaviour — shown
 * until it is withdrawn — so every announcement written before this column
 * existed reads exactly as it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengumuman', function (Blueprint $table) {
            $table->timestamp('berakhir_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('pengumuman', function (Blueprint $table) {
            $table->dropColumn('berakhir_at');
        });
    }
};
