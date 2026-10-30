<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A programme's accreditation decrees, one row per decree (ADR-041 §3).
 *
 * `prodi.akreditasi` held a grade and nothing else, so an expired decree
 * still read as accredited — and PISN refuses a diploma number, without
 * exception, to a graduate whose programme was not accredited on the day
 * they graduated. Which decree was in force on a past date is a question only
 * a history can answer, so a new decree is a new row and never overwrites
 * the one before it.
 *
 * `prodi.akreditasi` stays, as a cache of the latest grade written by
 * AkreditasiProdiService, for the screens that print it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akreditasi_prodi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();

            $table->string('lembaga', 24); // LembagaAkreditasi
            $table->string('peringkat', 32); // PeringkatAkreditasi
            $table->string('nomor_sk', 128);
            $table->date('tanggal_sk');

            // From when, until when. Usually the decree date and five years
            // on, but a decree can take effect later and is read as written.
            $table->date('tanggal_berlaku');
            $table->date('tanggal_kedaluwarsa');

            $table->string('dokumen_path')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('dicatat_oleh_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['prodi_id', 'tanggal_berlaku'], 'akreditasi_prodi_berlaku_index');
        });

        // The cached grade label: "Terakreditasi Sementara" does not fit in 16.
        Schema::table('prodi', function (Blueprint $table) {
            $table->string('akreditasi', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akreditasi_prodi');
    }
};
