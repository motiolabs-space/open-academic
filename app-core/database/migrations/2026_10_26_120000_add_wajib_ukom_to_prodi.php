<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a programme graduates nobody until the national competency
 * examination (uji kompetensi) is passed.
 *
 * Per programme, like wajib_tugas_akhir: within one health-sciences campus the
 * nursing programme needs it and the public-health programme may not.
 * Defaults to false. Unlike the final project, the examination applies to a
 * named minority of programmes, and switching it on for everyone would hold
 * every graduate of every other programme at "Menunggu Uji Kompetensi".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->boolean('wajib_ukom')->default(false)->after('wajib_tugas_akhir');
        });
    }

    public function down(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->dropColumn('wajib_ukom');
        });
    }
};
