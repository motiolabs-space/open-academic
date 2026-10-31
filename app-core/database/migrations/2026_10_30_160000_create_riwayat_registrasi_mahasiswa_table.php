<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `riwayat_registrasi_mahasiswa`: every time a student's registration was
 * replaced or reopened (ROADMAP #55, ADR-042 §3).
 *
 * A registration — one person, one programme, one NIM — is a student row
 * here and an `id_registrasi_mahasiswa` at PDDIKTI. An internal programme
 * transfer or a readmission starts a new one under a new NIM; the old row
 * keeps its NIM, its grades, and its own PDDIKTI registration, and this
 * table says the two belong to one person and why. Reactivation reopens the
 * same row and has no new one.
 *
 * The NIMs and programmes are copied, not only referenced: this row is the
 * record of what was true when the event happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_registrasi_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('jenis', 24); // JenisRegistrasiUlang

            $table->foreignId('mahasiswa_lama_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('mahasiswa_baru_id')->nullable()->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();
            $table->date('tanggal');

            $table->string('nim_lama', 32);
            $table->string('nim_baru', 32)->nullable();
            $table->foreignId('prodi_lama_id')->constrained('prodi')->restrictOnDelete();
            $table->foreignId('prodi_baru_id')->nullable()->constrained('prodi')->restrictOnDelete();

            // The old registration's status before and after the event.
            $table->char('status_sebelum', 1); // StudentStatus
            $table->char('status_sesudah', 1); // StudentStatus

            $table->string('jenis_daftar', 24)->nullable(); // JenisDaftar of the new registration

            $table->string('nomor_sk', 64)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->text('alasan'); // for the campus; never sent to PDDIKTI

            $table->foreignId('dicatat_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mahasiswa_lama_id', 'jenis'], 'riwayat_registrasi_lama_jenis_index');
            $table->index(['tahun_akademik_id', 'jenis'], 'riwayat_registrasi_term_jenis_index');
            $table->unique('mahasiswa_baru_id', 'riwayat_registrasi_baru_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_registrasi_mahasiswa');
    }
};
