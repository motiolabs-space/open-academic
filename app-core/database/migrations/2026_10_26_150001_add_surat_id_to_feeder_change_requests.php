<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The letter a PDM/PJK request was filed with.
 *
 * `nomor_surat` already records the number an operator typed in. When the
 * letter is issued here instead, the request also points at it, so the
 * console can hand back the PDF and the number cannot drift from the
 * document that carries it. One letter may carry several requests for the
 * same student; a request belongs to at most one letter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeder_change_requests', function (Blueprint $table) {
            $table->foreignId('surat_id')->nullable()->after('nomor_surat')
                ->constrained('surat')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feeder_change_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('surat_id');
        });
    }
};
