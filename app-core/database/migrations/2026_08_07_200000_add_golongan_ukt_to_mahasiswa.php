<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The student's UKT band.
 *
 * `tarif` has carried a `golongan_ukt` dimension since the first release, but
 * nothing on the student side ever held one — so that dimension could only ever
 * match the null wildcard, and the whole banded-fee system was dead in the
 * water.
 *
 * UKT (Uang Kuliah Tunggal) is means-tested by design: a campus assigns each
 * student a band from I to VIII based on household circumstances, and billing
 * everyone the same rate defeats the entire policy. The income figures that
 * decide the band are already collected on this table (`penghasilan_ortu`); the
 * band itself is the decision made from them, so it is stored rather than
 * recomputed — a family's circumstances at admission are what the band is based
 * on, and it must not silently shift when someone edits an income field years
 * later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table): void {
            $table->string('golongan_ukt', 16)->nullable()->after('jalur_masuk');

            // Bulk invoicing filters by band; without this it scans the whole
            // student table once per band.
            $table->index(['golongan_ukt', 'angkatan']);
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table): void {
            $table->dropIndex(['golongan_ukt', 'angkatan']);
            $table->dropColumn('golongan_ukt');
        });
    }
};
