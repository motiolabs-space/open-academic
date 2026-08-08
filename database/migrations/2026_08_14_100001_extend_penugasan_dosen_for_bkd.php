<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widening the existing assignment record rather than building a second one.
 *
 * `penugasan_dosen` already holds what a lecturer did outside the classroom:
 * type, title, partner, dates, an SKS equivalent, a document, and a verification
 * trail. Three different consumers now want that same fact:
 *
 *   - BKD, which needs it sorted into one of four elements and weighted in SKS
 *   - the SISTER portfolio, which needs the output it produced
 *   - IKU 3 and 4, which already read this table
 *
 * A parallel `kegiatan_bkd` table would mean a lecturer records a piece of
 * research twice and the two copies disagree by the second semester. Same
 * lesson as consolidating the three IPK calculations into PerolehanAkademik:
 * one record, several readers.
 *
 * All columns are nullable. Rows written before this migration are real
 * assignments that must keep working; classification is something a lecturer
 * adds when they build a report, not a precondition for having recorded the
 * activity in the first place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penugasan_dosen', function (Blueprint $table) {
            /*
             * Which of BKD's four elements this counts towards.
             *
             * Not derivable from `jenis`, which is why it is a column and not a
             * match statement. The same conference trip is "penelitian" when the
             * lecturer presented a paper and "penunjang" when they chaired the
             * organising committee, and only the person who went knows which.
             */
            $table->string('unsur', 24)->nullable()->after('jenis'); // UnsurBkd

            // Lead or member. The credit differs, and every ministry form asks.
            $table->string('peran', 24)->nullable()->after('unsur'); // PeranKegiatan

            // Local, national, international. Decides the weight in most rubrics
            // and is the axis IKU 5 is reported along.
            $table->string('tingkat', 24)->nullable()->after('peran'); // TingkatKegiatan

            /*
             * What came out of it, and how to find it.
             *
             * One pair of columns rather than a table per output type. A DOI, an
             * ISBN, a patent number, and a repository URL are all "the string
             * somebody types to locate this thing" — modelling them separately
             * would produce four nearly-empty tables and a join for every report.
             */
            $table->string('luaran_jenis', 32)->nullable()->after('tingkat'); // JenisLuaran
            $table->string('luaran_identitas')->nullable()->after('luaran_jenis');
            $table->year('luaran_tahun')->nullable()->after('luaran_identitas');

            $table->index(['dosen_id', 'tahun_akademik_id'], 'penugasan_dosen_bkd_index');
        });
    }

    public function down(): void
    {
        Schema::table('penugasan_dosen', function (Blueprint $table) {
            $table->dropIndex('penugasan_dosen_bkd_index');

            $table->dropColumn([
                'unsur',
                'peran',
                'tingkat',
                'luaran_jenis',
                'luaran_identitas',
                'luaran_tahun',
            ]);
        });
    }
};
