<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lecturer's own record — the half of national reporting Open Academic has
 * never held.
 *
 * Neo Feeder reports students. SISTER reports the people teaching them, and it
 * asks for histories rather than current values: every degree, not the highest
 * one; every functional rank with its decree and credit score, not the label
 * somebody typed today.
 *
 * The `dosen` table carries flat columns for the current state
 * (`pendidikan_tertinggi`, `jabatan_fungsional`) because that is what a class
 * list and a signature block need. Those stay. These tables are the history
 * behind them, and where the two disagree the history wins — it is the one with
 * dates and documents attached.
 *
 * Nothing here is invented for this codebase. Every column exists because a
 * ministry form asks for it, which is also why several are nullable that
 * "should" be required: a campus migrating twenty years of staff records will
 * not have a scan of a 2003 decree, and refusing the row entirely would mean
 * the twenty years never get entered at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Every degree held, not just the highest.
         *
         * BKD and accreditation both ask questions the highest degree cannot
         * answer — whether a lecturer's master's is in the field they teach, for
         * instance, which is a linearity question about a specific degree.
         */
        Schema::create('riwayat_pendidikan_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('jenjang', 8);              // EducationLevel
            $table->string('perguruan_tinggi');
            $table->string('program_studi')->nullable();
            $table->string('bidang_ilmu')->nullable();

            // Foreign degrees need a recognition decree (penyetaraan ijazah)
            // before they count, so the country is not decoration.
            $table->string('negara', 64)->default('Indonesia');

            $table->year('tahun_masuk')->nullable();
            $table->year('tahun_lulus')->nullable();
            $table->string('gelar', 32)->nullable();
            $table->string('nomor_ijazah', 64)->nullable();

            $table->string('dokumen_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'jenjang']);
        });

        /*
         * The functional rank ladder, with its credit score.
         *
         * Angka kredit accumulates across a career and decides promotion, so the
         * value belongs to the *appointment*, not to the person: overwriting one
         * number on `dosen` each time would erase the ladder that justifies the
         * current rung.
         *
         * Stored as an integer of hundredths (`angka_kredit_ratus`), the same
         * reasoning as money. Credit is awarded in quarter points and summed
         * across decades; a float would drift, and the drift would show up in a
         * promotion decision.
         */
        Schema::create('jabatan_fungsional_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('jabatan', 64);             // JabatanFungsional
            $table->unsignedInteger('angka_kredit_ratus')->default(0);

            $table->string('nomor_sk', 96)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tmt');                       // terhitung mulai tanggal

            $table->string('dokumen_path')->nullable();

            /*
             * Which rung is the current one.
             *
             * Nullable-unique holding a composite key while current and NULL
             * otherwise: NULLs do not collide on either MySQL or PostgreSQL, so
             * this is the portable way to say "at most one". A partial index
             * (WHERE is_current) is not portable. Same pattern as
             * tugas_akhir.mahasiswa_aktif_id.
             */
            $table->foreignId('dosen_aktif_id')->nullable()->unique()
                ->constrained('dosen')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'tmt']);
        });

        /*
         * Certificates: Serdos above all, then competence and profession.
         *
         * Serdos is the one with money attached — it is what makes a BKD report
         * consequential rather than paperwork — so its number and date are the
         * fields most likely to be typed wrong and most worth storing once.
         */
        Schema::create('sertifikasi_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('jenis', 32);               // JenisSertifikasi
            $table->string('nama');
            $table->string('nomor', 96)->nullable();
            $table->string('penyelenggara')->nullable();
            $table->string('bidang')->nullable();

            $table->date('tanggal');

            // Null means it does not expire. Serdos does not; a competence
            // certificate from industry usually does, and an expired one is
            // still a true historical fact — hence a date rather than a flag.
            $table->date('berlaku_sampai')->nullable();

            $table->string('dokumen_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikasi_dosen');
        Schema::dropIfExists('jabatan_fungsional_dosen');
        Schema::dropIfExists('riwayat_pendidikan_dosen');
    }
};
