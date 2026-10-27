<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `feeder_past_students` is the report of one read of PDDIKTI's past-student
 * list (GetListDaftarLampau): students PDDIKTI holds for this campus that
 * its Feeder has never claimed, for one programme and one intake term, each
 * with what the adoption matcher made of it.
 *
 * Unclaimed rows are findings and are replaced by the next read. A claimed
 * row is a fact about PDDIKTI and stays, with who claimed it and why — the
 * ledger records the web-service call, this row the person behind it.
 *
 * No NIK is stored, for the same reason as `feeder_adoptions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeder_past_students', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->uuid('batch_id');
            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();
            $table->char('periode', 5); // PDDIKTI term code of the intake (id_periode_masuk)

            $table->string('status', 16); // StatusLampauFeeder
            $table->string('alasan', 24)->nullable(); // AlasanAmbiguAdopsi, for ambiguous rows

            // The local student the matcher pointed at, if any.
            $table->foreignId('mahasiswa_id')->nullable()->constrained('mahasiswa')->restrictOnDelete();

            // What PDDIKTI holds.
            $table->string('feeder_mahasiswa_id', 64)->nullable();
            $table->string('feeder_registrasi_id', 64);
            $table->string('nim_feeder', 32)->nullable();
            $table->string('nama_feeder')->nullable();
            $table->date('tanggal_lahir_feeder')->nullable();
            $table->boolean('nik_cocok')->nullable();

            // The fields the claim act is sent, as listed; null when the row
            // lacks one of them and so cannot be claimed.
            $table->json('kunci_klaim')->nullable();

            // A claim that went through.
            $table->text('catatan')->nullable();
            $table->foreignId('diklaim_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diklaim_at')->nullable();

            $table->timestamps();

            $table->index(['prodi_id', 'periode', 'status'], 'feeder_past_students_prodi_periode_status_index');
            $table->index('feeder_registrasi_id', 'feeder_past_students_registrasi_index');
            $table->index(['mahasiswa_id', 'status'], 'feeder_past_students_mahasiswa_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_past_students');
    }
};
