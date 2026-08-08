<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A covering index for the attendance recap.
 *
 * The recap asks, per meeting, which students were not absent. The existing
 * unique index is (pertemuan_kelas_id, mahasiswa_id), which finds the meeting's
 * rows quickly but does not carry `status` — so the engine has to read every
 * matching row off disk just to discard the absences.
 *
 * Measured on a campus with 635k attendance records: the aggregate behind the
 * lecturer's class list took 6.2s without this index. Attendance is the largest
 * table in the system by an order of magnitude — a full-time student generates
 * roughly 128 rows per semester — so it is the one place where an index that
 * merely "helps" is not enough and the query has to be answerable from the
 * index alone.
 *
 * Column order matters: equality on pertemuan_kelas_id first, then status to
 * filter inside that range, then mahasiswa_id so the grouping is served without
 * touching the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table): void {
            $table->index(
                ['pertemuan_kelas_id', 'status', 'mahasiswa_id'],
                'presensi_rekap_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table): void {
            $table->dropIndex('presensi_rekap_index');
        });
    }
};
