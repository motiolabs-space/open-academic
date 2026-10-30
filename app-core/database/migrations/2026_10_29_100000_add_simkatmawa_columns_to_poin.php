<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a SIMKATMAWA report needs about an achievement and the ledger did not
 * hold (ADR-037 §1).
 *
 * The points ledger was built to decide whether a student may graduate: a
 * title, a date, a category and a point value. Reporting the same achievement
 * to the ministry's student-affairs system needs the facts the ministry
 * scores it by — the level it was contested at, the place won, who organised
 * it, how many institutions or countries took part, and links an assessor can
 * open. All nullable: rows recorded before this existed, and violations, stay
 * valid; the export reports what is missing instead of refusing the table.
 *
 * Which SIMKATMAWA category a row belongs to lives on the catalogue entry,
 * not on each row: "juara lomba tingkat nasional" is always a competition,
 * and a staff member recording forty of them should not pick that forty times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poin_kategori', function (Blueprint $table) {
            // KategoriSimkatmawa; null = not reported to SIMKATMAWA at all.
            $table->string('simkatmawa_kategori', 16)->nullable()->after('tingkat');
        });

        Schema::table('poin_mahasiswa', function (Blueprint $table) {
            // TingkatSimkatmawa. The catalogue's own `tingkat` vocabulary
            // (internal/lokal/…) predates SIMKATMAWA and cannot tell a
            // district contest from a provincial one.
            $table->string('tingkat', 24)->nullable()->after('jenis');

            $table->string('peringkat', 16)->nullable()->after('tingkat');          // PeringkatPrestasi
            $table->string('bentuk_kepesertaan', 16)->nullable()->after('peringkat'); // individu|kelompok
            $table->string('moda_pelaksanaan', 16)->nullable()->after('bentuk_kepesertaan'); // daring|luring
            $table->string('bidang_lomba', 24)->nullable()->after('moda_pelaksanaan'); // BidangLomba
            $table->string('jenis_rekognisi', 32)->nullable()->after('bidang_lomba'); // JenisRekognisi

            $table->string('penyelenggara')->nullable()->after('keterangan');
            $table->date('tanggal_selesai')->nullable()->after('tanggal');

            // The ministry scores a contest by its field size: institutions
            // for district to national, countries for international.
            $table->unsignedSmallInteger('jumlah_pt_peserta')->nullable();
            $table->unsignedSmallInteger('jumlah_negara_peserta')->nullable();

            $table->string('nomor_sertifikat', 100)->nullable();

            // Links, not uploads: SIMKATMAWA takes URLs an assessor can open.
            $table->string('tautan_sertifikat', 500)->nullable();
            $table->string('tautan_bukti', 500)->nullable();
            $table->string('tautan_surat_tugas', 500)->nullable();

            // The supervising lecturer named on the assignment letter. Restrict,
            // not null-on-delete: a supervision that was reported must not lose
            // its lecturer quietly (CLAUDE.md §8).
            $table->foreignId('dosen_pembimbing_id')->nullable()
                ->constrained('dosen', indexName: 'poin_mhs_dosen_pembimbing_fk')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('poin_mahasiswa', function (Blueprint $table) {
            $table->dropForeign('poin_mhs_dosen_pembimbing_fk');
            $table->dropColumn([
                'tingkat', 'peringkat', 'bentuk_kepesertaan', 'moda_pelaksanaan', 'bidang_lomba',
                'jenis_rekognisi', 'penyelenggara', 'tanggal_selesai', 'jumlah_pt_peserta',
                'jumlah_negara_peserta', 'nomor_sertifikat', 'tautan_sertifikat', 'tautan_bukti',
                'tautan_surat_tugas', 'dosen_pembimbing_id',
            ]);
        });

        Schema::table('poin_kategori', function (Blueprint $table) {
            $table->dropColumn('simkatmawa_kategori');
        });
    }
};
