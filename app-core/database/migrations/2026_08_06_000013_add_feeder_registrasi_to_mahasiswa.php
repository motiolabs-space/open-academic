<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDDIKTI gives a student two identifiers, and they are not interchangeable:
 *
 *  - id_mahasiswa           from InsertBiodataMahasiswa — the person
 *  - id_registrasi_mahasiswa from InsertRiwayatPendidikanMahasiswa — the
 *                            enrolment of that person into a programme
 *
 * Every later payload (aktivitas kuliah, KRS, nilai) references the second one.
 * Storing both in a single column would work right up until a student changes
 * programme, at which point the wrong identifier would be reported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->string('feeder_registrasi_id', 64)->nullable()->index()->after('feeder_id');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropColumn('feeder_registrasi_id');
        });
    }
};
