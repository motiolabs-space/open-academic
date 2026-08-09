<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poin kemahasiswaan — two ledgers that are never netted against each other.
 *
 * Achievements and violations share a shape but not a total. Summing them would
 * let a student pay off a sanction with a competition win, which is not what any
 * student-affairs office means by either number.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * The catalogue: what a campus recognises and what it is worth.
         *
         * In the database rather than in config, unlike the thresholds. This
         * list runs to dozens of rows, differs by campus, and is revised every
         * year by the people who administer it — none of which is true of a
         * threshold, and all of which argues for a screen rather than a deploy.
         */
        Schema::create('poin_kategori', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('kode', 24)->unique();
            $table->string('nama');

            // JenisPoin: prestasi | pelanggaran
            $table->string('jenis', 16);

            // Free-ish, validated against config('kemahasiswaan.tingkat').
            $table->string('tingkat', 24)->nullable();

            $table->unsignedSmallInteger('poin');
            $table->string('keterangan', 500)->nullable();

            /*
             * Whether a claim needs a document attached.
             *
             * A first-place certificate can be produced; being late to a lecture
             * cannot. Requiring evidence for everything makes staff fabricate
             * it, which is worse than not asking.
             */
            $table->boolean('wajib_bukti')->default(true);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['jenis', 'is_active']);
        });

        Schema::create('poin_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('poin_kategori_id')->constrained('poin_kategori')->restrictOnDelete();

            // Nullable: violations and off-campus achievements do not always
            // belong to a term the campus is running.
            $table->foreignId('tahun_akademik_id')->nullable()->constrained('tahun_akademik')->nullOnDelete();

            $table->date('tanggal');
            $table->string('judul');
            $table->text('keterangan')->nullable();

            /*
             * The point value as it stood, copied from the catalogue.
             *
             * A campus that re-prices "juara 1 tingkat nasional" from 40 to 60
             * next year must not silently rewrite what last year's graduates
             * were credited with. Same reason a BKD line and an issued letter
             * freeze their contents.
             */
            $table->unsignedSmallInteger('poin');

            // JenisPoin, copied too: the catalogue row could in principle be
            // corrected, and a ledger that changes sides retroactively is worse
            // than one that is slightly stale.
            $table->string('jenis', 16);

            $table->string('bukti_path')->nullable();

            /*
             * Unverified rows count for nothing.
             *
             * Both directions matter: an unverified achievement must not push a
             * student over the graduation line, and an unverified allegation
             * must not sit against their name as though it were established.
             */
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('alasan_tolak', 500)->nullable();

            $table->foreignId('dicatat_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mahasiswa_id', 'jenis', 'is_verified']);
            $table->index(['is_verified', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poin_mahasiswa');
        Schema::dropIfExists('poin_kategori');
    }
};
