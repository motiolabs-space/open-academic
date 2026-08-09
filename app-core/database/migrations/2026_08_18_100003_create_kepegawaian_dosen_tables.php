<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deeper personnel records, continuing the three history tables from G7.
 *
 * Typed tables rather than one generic "riwayat" with a JSON blob, matching
 * riwayat_pendidikan_dosen / jabatan_fungsional_dosen / sertifikasi_dosen. A
 * generic table cannot be validated, cannot be indexed usefully, and cannot be
 * mapped to a SISTER field without a lookup nobody maintains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dosen', function (Blueprint $table) {
            /*
             * Where this lecturer currently works.
             *
             * `prodi_id` says which programme they teach for; this says which
             * unit employs them, and the two genuinely differ — a lecturer
             * seconded to the library still teaches. Kept as a pointer and
             * updated by the mutation record, so there is one current answer
             * rather than a derivation that can disagree with the history.
             */
            $table->foreignId('unit_kerja_id')->nullable()->after('prodi_id')
                ->constrained('unit_kerja')->nullOnDelete();
        });

        Schema::create('keluarga_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('nama');
            $table->string('hubungan', 24); // pasangan | anak | ayah | ibu
            $table->date('tanggal_lahir')->nullable();
            $table->string('pekerjaan')->nullable();

            // Drives allowances and insurance lists; a spouse who is not a
            // dependant is still a spouse.
            $table->boolean('is_tanggungan')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'hubungan']);
        });

        Schema::create('pangkat_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('pangkat', 64);      // Penata Muda, Pembina, …
            $table->string('golongan', 8);      // III/a … IV/e
            $table->date('tmt');
            $table->string('nomor_sk', 128)->nullable();
            $table->date('tanggal_sk')->nullable();

            /*
             * Which rank is the current one.
             *
             * Same portable "at most one" as jabatan_fungsional_dosen: a
             * nullable-unique column holding the lecturer id while current and
             * NULL otherwise. NULLs do not collide on MySQL or PostgreSQL, and
             * a partial index is not portable.
             */
            $table->foreignId('dosen_aktif_id')->nullable()->unique()
                ->constrained('dosen')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'tmt']);
        });

        Schema::create('mutasi_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('jenis', 24); // masuk | pindah | keluar

            // Both nullable: an arrival has no origin, a departure no destination.
            $table->foreignId('unit_asal_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->foreignId('unit_tujuan_id')->nullable()->constrained('unit_kerja')->nullOnDelete();

            $table->date('tmt');
            $table->string('nomor_sk', 128)->nullable();
            $table->string('keterangan', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'tmt']);
        });

        Schema::create('penghargaan_sanksi_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            /*
             * One table, one discriminator — but never one total.
             *
             * These share every column and no arithmetic. Nothing in the
             * application adds them, subtracts them, or presents a balance:
             * an award does not offset a reprimand, and a screen that implied
             * otherwise would be making a judgement the campus never made.
             */
            $table->string('jenis', 16); // penghargaan | sanksi

            $table->string('nama');
            $table->string('tingkat', 24)->nullable();
            $table->string('pemberi')->nullable();
            $table->date('tanggal');
            $table->string('nomor_sk', 128)->nullable();
            $table->string('keterangan', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'jenis']);
        });

        Schema::create('bahasa_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('bahasa', 64);
            $table->string('kemampuan', 24)->nullable(); // dasar | menengah | mahir | penutur asli
            $table->string('sertifikat', 128)->nullable();
            $table->unsignedSmallInteger('skor')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['dosen_id', 'bahasa']);
        });

        Schema::create('organisasi_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->string('nama');
            $table->string('peran', 64)->nullable();
            $table->string('tingkat', 24)->nullable();
            $table->unsignedSmallInteger('tahun_mulai');

            // Null means still a member — the ordinary case, and the reason
            // this is not a required pair of dates.
            $table->unsignedSmallInteger('tahun_selesai')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dosen_id', 'tahun_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisasi_dosen');
        Schema::dropIfExists('bahasa_dosen');
        Schema::dropIfExists('penghargaan_sanksi_dosen');
        Schema::dropIfExists('mutasi_dosen');
        Schema::dropIfExists('pangkat_dosen');
        Schema::dropIfExists('keluarga_dosen');

        Schema::table('dosen', function (Blueprint $table) {
            $table->dropForeign(['unit_kerja_id']);
            $table->dropColumn('unit_kerja_id');
        });
    }
};
