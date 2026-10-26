<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `feeder_adoptions` is the report of one adoption run: for one programme
 * and one intake term, which local students were matched to a student
 * PDDIKTI already holds, which exist on one side only, and which could not
 * be decided automatically.
 *
 * A campus arriving from another SIAKAD already has every student at
 * PDDIKTI. Adoption reads them back and records their Feeder identifiers
 * here and on `mahasiswa`, without sending anything, so the first sync
 * updates or skips instead of registering the same people a second time.
 *
 * No NIK is stored. It is the strongest key for matching and the one field
 * that must not end up copied into a report table and onto a screen that
 * lists hundreds of students; the row records whether it matched, not what
 * it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeder_adoptions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->uuid('batch_id');
            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();
            $table->char('periode', 5); // PDDIKTI term code of the intake (id_periode_masuk)

            $table->string('status', 16); // StatusAdopsiFeeder
            $table->string('alasan', 24)->nullable(); // AlasanAmbiguAdopsi, for ambiguous rows

            $table->foreignId('mahasiswa_id')->nullable()->constrained('mahasiswa')->restrictOnDelete();

            // What PDDIKTI holds: the person and the enrolment.
            $table->string('feeder_mahasiswa_id', 64)->nullable();
            $table->string('feeder_registrasi_id', 64)->nullable();
            $table->string('nim_feeder', 32)->nullable();
            $table->string('nama_feeder')->nullable();

            // Every Feeder row this local student could be, without NIK.
            $table->json('kandidat')->nullable();

            // A decision made by a person, for a row the matcher would not decide.
            $table->text('catatan')->nullable();
            $table->foreignId('diputuskan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diputuskan_at')->nullable();

            $table->timestamps();

            $table->index(['prodi_id', 'periode', 'status'], 'feeder_adoptions_prodi_periode_status_index');
            $table->index(['mahasiswa_id', 'status'], 'feeder_adoptions_mahasiswa_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_adoptions');
    }
};
