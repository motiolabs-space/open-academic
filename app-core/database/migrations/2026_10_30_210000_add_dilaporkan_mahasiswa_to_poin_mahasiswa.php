<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who reported an achievement from the student portal (ADR-040 §3).
 *
 * Null for every row staff recorded, which is every row before this. A
 * student-reported row waits in the same verification queue and counts for
 * nothing until staff verify it; the column only says where it came from, so
 * the student can be told the outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poin_mahasiswa', function (Blueprint $table) {
            $table->foreignId('dilaporkan_by_mahasiswa_id')->nullable()->after('dicatat_by_staff_id')
                ->constrained('mahasiswa')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('poin_mahasiswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dilaporkan_by_mahasiswa_id');
        });
    }
};
