<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two per-programme reference tables PDDIKTI insists on, and the ledger of
 * corrections it refuses to take through the web service.
 *
 * `skala_nilai_prodi` and `periode_perkuliahan` are the local rows behind
 * InsertSkalaNilaiProdi and InsertPeriodePerkuliahan. Both are per programme
 * at PDDIKTI while the campus keeps one grading scale (config/academic.php)
 * and one calendar (tahun_akademik). The rows here are *derived* from those —
 * one per programme and letter, one per programme and term — so a programme
 * that must differ from the campus default edits its own rows without the
 * code learning about it. See docs/DECISIONS.md, ADR-016.
 *
 * `feeder_change_requests` records a change to a column PDDIKTI locks once a
 * student is reported: the six identity columns (PDM, Perubahan Data
 * Mahasiswa) and the seven exit columns (PJK, Perubahan Jenis Keluar). The
 * web service accepts a few of those; the rest are filed at PDDIKTI admin
 * with a letter. Either way the change is recorded the moment it happens,
 * never quietly re-sent as if nothing were locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skala_nilai_prodi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();
            $table->string('nilai_huruf', 4);
            $table->decimal('nilai_indeks', 3, 2);
            $table->decimal('bobot_minimum', 5, 2);
            $table->decimal('bobot_maksimum', 5, 2);
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();

            $table->string('feeder_id', 64)->nullable()->index();
            $table->timestamp('feeder_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['prodi_id', 'nilai_huruf', 'berlaku_mulai'], 'skala_nilai_prodi_prodi_huruf_mulai_unique');
        });

        Schema::create('periode_perkuliahan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->date('tanggal_awal');
            $table->date('tanggal_akhir');
            $table->unsignedSmallInteger('jumlah_minggu_pertemuan');

            // Intake figures PDDIKTI asks for on the same record. Null means
            // "not counted", which is what a programme without an admission
            // wave in that term reports — not zero applicants.
            $table->unsignedSmallInteger('target_mahasiswa_baru')->nullable();
            $table->unsignedSmallInteger('pendaftar_ikut_seleksi')->nullable();
            $table->unsignedSmallInteger('pendaftar_lulus_seleksi')->nullable();
            $table->unsignedSmallInteger('daftar_ulang')->nullable();
            $table->unsignedSmallInteger('mengundurkan_diri')->nullable();

            $table->boolean('metode_tatap_muka')->default(true);
            $table->boolean('metode_daring')->default(false);
            $table->boolean('metode_campuran')->default(false);

            $table->string('feeder_id', 64)->nullable()->index();
            $table->timestamp('feeder_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['prodi_id', 'tahun_akademik_id'], 'periode_perkuliahan_prodi_term_unique');
        });

        Schema::create('feeder_change_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            $table->string('jenis', 8); // JenisPerubahanFeeder: pdm | pjk
            $table->string('kolom', 32); // the locked column, in PDDIKTI's vocabulary
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru')->nullable();

            $table->string('jalur', 16); // JalurPerubahanFeeder
            $table->string('status', 16)->default('menunggu'); // StatusPerubahanFeeder

            // The paper trail for the PDDIKTI-admin route.
            $table->string('nomor_surat', 96)->nullable();
            $table->text('catatan')->nullable();

            $table->timestamp('diajukan_at')->nullable();
            $table->timestamp('dikirim_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('diproses_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mahasiswa_id', 'status'], 'feeder_change_requests_mahasiswa_status_index');
            $table->index(['status', 'jalur'], 'feeder_change_requests_status_jalur_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_change_requests');
        Schema::dropIfExists('periode_perkuliahan');
        Schema::dropIfExists('skala_nilai_prodi');
    }
};
