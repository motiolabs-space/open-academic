<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What IKU 6 asks of a publication, as facts on the output (ADR-041 §2).
 *
 * The index it is reported under and its quartile, the DOI that lets two
 * co-authors' rows be recognised as one article, a link a verifier can
 * open, the publisher (the decree leaves some publishers out), and whether
 * a co-author is from abroad (the decree adds weight for it). Declared by
 * the lecturer, checked by staff; nothing here calls an indexing service.
 *
 * Nullable: only journal articles and proceedings carry them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasan_dosen', function (Blueprint $table) {
            $table->string('luaran_indeks', 24)->nullable()->after('luaran_tahun'); // IndeksPublikasi
            $table->string('luaran_kuartil', 4)->nullable()->after('luaran_indeks'); // KuartilPublikasi
            $table->string('luaran_doi')->nullable()->after('luaran_kuartil');
            $table->string('luaran_tautan', 512)->nullable()->after('luaran_doi');
            $table->string('luaran_penerbit')->nullable()->after('luaran_tautan');
            $table->boolean('luaran_kolaborasi_internasional')->nullable()->after('luaran_penerbit');
        });
    }

    public function down(): void
    {
        Schema::table('penugasan_dosen', function (Blueprint $table) {
            $table->dropColumn([
                'luaran_indeks',
                'luaran_kuartil',
                'luaran_doi',
                'luaran_tautan',
                'luaran_penerbit',
                'luaran_kolaborasi_internasional',
            ]);
        });
    }
};
