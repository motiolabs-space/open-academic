<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The term a transfer student first enrolled at the institution they came from.
 *
 * PDDIKTI does not ask for it — the enrolment record carries only the origin's
 * name, programme and the credits recognised. It exists for one campus rule:
 * whether the maximum study period runs from the start of study here or from
 * the start of study anywhere. See `academic.pindahan.masa_studi_dari_asal`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->char('term_masuk_asal', 5)->nullable()->after('prodi_asal'); // PDDIKTI term code
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropColumn('term_masuk_asal');
        });
    }
};
