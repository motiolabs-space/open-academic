<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The teaching journal — berita acara perkuliahan.
 *
 * `pertemuan_kelas` already records *who attended*. This records *what was
 * taught*, which is the half that gets asked about during monitoring and the
 * half that is still written on paper at most campuses.
 *
 * Columns on the existing row rather than a parallel table: it is strictly
 * one-to-one with a meeting, and a second table would only add a join to every
 * attendance screen for data that belongs to the same event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertemuan_kelas', function (Blueprint $table) {
            /*
             * Which planned session this meeting actually delivered.
             *
             * Nullable and deliberately not forced to match `pertemuan_ke`.
             * Teaching slips: week 5 gets delivered in week 6 after a public
             * holiday, and two planned sessions get merged. Recording what was
             * really covered is the whole point; making the journal agree with
             * the plan by construction would delete the information that a
             * monitor is looking for.
             */
            $table->foreignId('rps_pertemuan_id')->nullable()->after('topik')
                ->constrained('rps_pertemuan')->nullOnDelete();

            $table->text('materi')->nullable()->after('rps_pertemuan_id');
            $table->text('catatan')->nullable()->after('materi');

            /*
             * Attendance counts frozen at the moment the journal was signed.
             *
             * Derived from `presensi` at signing and never recomputed. A journal
             * is a statement about one day; recomputing it months later — after a
             * correction, or after a student's enrolment was withdrawn — would
             * change a signed record of what happened in a room.
             */
            $table->unsignedSmallInteger('jumlah_hadir')->nullable()->after('catatan');
            $table->unsignedSmallInteger('jumlah_peserta')->nullable()->after('jumlah_hadir');

            $table->timestamp('jurnal_diisi_at')->nullable()->after('jumlah_peserta');
            $table->foreignId('jurnal_oleh_dosen_id')->nullable()->after('jurnal_diisi_at')
                ->constrained('dosen')->nullOnDelete();

            $table->index(['kelas_kuliah_id', 'jurnal_diisi_at'], 'pertemuan_jurnal_index');
        });
    }

    public function down(): void
    {
        Schema::table('pertemuan_kelas', function (Blueprint $table) {
            $table->dropIndex('pertemuan_jurnal_index');
            $table->dropForeign(['rps_pertemuan_id']);
            $table->dropForeign(['jurnal_oleh_dosen_id']);

            $table->dropColumn([
                'rps_pertemuan_id', 'materi', 'catatan',
                'jumlah_hadir', 'jumlah_peserta',
                'jurnal_diisi_at', 'jurnal_oleh_dosen_id',
            ]);
        });
    }
};
