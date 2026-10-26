<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The credit ledger, and the identifier the ministry matches it by.
 *
 * One row per credited item per element (Kum A–E), in hundredths. Derived
 * rows point at their source — an endorsed BKD report or a degree on the
 * portfolio — and are rebuilt from it; office-entered rows carry the staff
 * member and, for an adjustment, the reason. Nothing is ever summed into a
 * stored total: the recap is computed, so a correction to one line is a
 * correction to every figure built on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dosen', function (Blueprint $table) {
            // NUPTK replaced NIDN as the national lecturer identifier, and the
            // penyetaraan upload matches rows by it and the name together.
            $table->string('nuptk', 16)->nullable()->after('nidn');
            $table->index('nuptk', 'dosen_nuptk_idx');
        });

        Schema::create('angka_kredit_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen', indexName: 'akd_dosen_fk')->restrictOnDelete();

            $table->string('unsur', 16);  // UnsurAngkaKredit
            $table->string('sumber', 24); // SumberAngkaKredit

            // Provenance for derived rows. nullOnDelete, like bkd_baris: the
            // ledger keeps what was credited even if the source is removed,
            // and the next reassembly is what decides whether it still stands.
            $table->foreignId('bkd_laporan_id')->nullable()
                ->constrained('bkd_laporan', indexName: 'akd_bkd_fk')->nullOnDelete();
            $table->foreignId('riwayat_pendidikan_dosen_id')->nullable()
                ->constrained('riwayat_pendidikan_dosen', indexName: 'akd_pendidikan_fk')->nullOnDelete();

            // A semester code for performance from before this application
            // existed, when there is no tahun_akademik row to point at.
            $table->char('semester_kode', 5)->nullable();

            // When the credited performance was completed — what the recap
            // compares with the TMT of the rank in force.
            $table->date('tanggal');

            $table->integer('sks_ratus')->nullable();
            $table->integer('faktor_ratus')->nullable();

            // Signed: an adjustment may take credit away, with its reason.
            $table->integer('nilai_ratus');

            // True when the factor was the campus's estimate for performance
            // from 2026 on, rather than the regulation's conversion.
            $table->boolean('perkiraan')->default(false);

            $table->string('keterangan', 500)->nullable();
            $table->foreignId('dicatat_oleh')->nullable()
                ->constrained('staff', indexName: 'akd_staff_fk')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // One line per source per element: reassembling twice updates the
            // same rows instead of crediting the semester twice.
            $table->unique(['bkd_laporan_id', 'unsur'], 'akd_bkd_unsur_unique');
            $table->unique(['riwayat_pendidikan_dosen_id', 'unsur'], 'akd_pendidikan_unsur_unique');
            $table->index(['dosen_id', 'tanggal'], 'akd_dosen_tanggal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('angka_kredit_dosen');

        Schema::table('dosen', function (Blueprint $table) {
            $table->dropIndex('dosen_nuptk_idx');
            $table->dropColumn('nuptk');
        });
    }
};
