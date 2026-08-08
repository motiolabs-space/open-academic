<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit recognised from somewhere other than this campus.
 *
 * Two situations, one table. A student transferring in has a transcript from
 * another institution; a student admitted through RPL has work or training that
 * an assessor judged equivalent. Both end in the same statement: *this local
 * course is satisfied, for this many credits, on this evidence.*
 *
 * `pmb_gelombang.jalur` has accepted "rpl" and "transfer" since the admissions
 * module was built, with nowhere to record what was recognised. A student
 * admitted into their fifth semester therefore arrived with an empty transcript
 * and a graduation requirement they could not reach — the door was open and
 * there was no floor behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konversi_kredit', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();

            $table->string('jenis', 16)->index();  // JenisKonversi
            $table->string('status', 16)->index(); // StatusKonversi

            /*
             * Where it came from.
             *
             * asal_institusi is null for RPL, where the source is employment or
             * training rather than another campus. asal_nama carries the course
             * title or a description of the experience — it is printed on the
             * conversion decision and is what an auditor reads years later.
             */
            $table->string('asal_institusi')->nullable();
            $table->string('asal_nama');
            $table->unsignedTinyInteger('asal_sks')->nullable();
            $table->string('asal_nilai', 8)->nullable();

            // What the campus decided to grant, which may be less.
            $table->unsignedTinyInteger('sks_diakui');
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot', 3, 2)->nullable();

            /*
             * The evidence.
             *
             * A conversion without a document behind it is a claim. Stored on
             * the private disk like every other supporting file — these are
             * transcripts from other institutions and employment letters.
             */
            $table->string('berkas_path')->nullable();

            $table->string('catatan', 500)->nullable();

            $table->foreignId('diputus_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diputus_at')->nullable();

            /*
             * One approved conversion per course per student, held by the
             * database rather than by a check somebody can forget.
             *
             * Carries "{mahasiswa_id}:{mata_kuliah_id}" while approved and NULL
             * otherwise, so rejected and withdrawn proposals coexist freely
             * while a second grant for the same course is refused outright.
             * NULLs do not collide under a unique index on either MySQL or
             * PostgreSQL — the same shape as tugas_akhir.mahasiswa_aktif_id, and
             * for the same reason: a partial index is not portable.
             *
             * What it prevents is double-counting. A course credited twice adds
             * its credits twice to a graduation total, and nothing else in the
             * system would notice.
             */
            $table->string('kunci_aktif', 48)->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mahasiswa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konversi_kredit');
    }
};
