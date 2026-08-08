<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BKD — the workload report a certified lecturer files each semester.
 *
 * The report is assessed, and the assessment gates an allowance. That single
 * fact decides the whole shape of these tables: **a report that has been
 * assessed must never change afterwards.**
 *
 * Teaching load is derived from live data — classes, supervision, examining,
 * advising. Live data keeps moving. A class gets reassigned in April, a
 * supervisor is swapped, a room booking is corrected. If the report read
 * through to those rows, an assessment signed in March would quietly become an
 * assessment of something else, and the signature would be attached to numbers
 * nobody ever saw.
 *
 * So `bkd_baris` is a snapshot, written at submission and never recomputed.
 * Before submission the worksheet is computed on the fly and stores nothing;
 * after submission the stored lines are the report. Same principle as the
 * frozen content of an issued letter and the frozen wording of an EDOM
 * questionnaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bkd_laporan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('status', 24)->default('draft'); // StatusBkd

            /*
             * Totals per element, in hundredths of an SKS.
             *
             * Integers rather than decimals, for the same reason money is: these
             * are summed across a dozen lines and compared against a threshold
             * that decides whether an allowance is paid. A rounding drift of
             * 0.01 either side of 12.00 is the difference between a passed and a
             * failed report.
             *
             * Denormalised onto the report deliberately — they are the assessed
             * figures, and recomputing them from the lines on every read would
             * invite the two to disagree.
             */
            $table->unsignedInteger('sks_pendidikan')->default(0);
            $table->unsignedInteger('sks_penelitian')->default(0);
            $table->unsignedInteger('sks_pengabdian')->default(0);
            $table->unsignedInteger('sks_penunjang')->default(0);
            $table->unsignedInteger('sks_total')->default(0);

            $table->timestamp('diajukan_at')->nullable();

            /*
             * The assessor, and the second one.
             *
             * Two is the convention, and it matters that they are two named
             * people rather than a role: an assessment is a judgement somebody
             * signs, and a report approved by "the system" is a report nobody
             * checked.
             */
            $table->foreignId('asesor_1_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();
            $table->foreignId('asesor_2_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();

            $table->timestamp('dinilai_at')->nullable();
            $table->string('kesimpulan', 24)->nullable(); // KesimpulanBkd
            $table->text('catatan_asesor')->nullable();

            $table->foreignId('disahkan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('disahkan_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * One report per lecturer per semester.
             *
             * Held by the database rather than by a check-then-insert, because
             * two browser tabs on the last day before the deadline is exactly
             * how a duplicate gets created — and two reports for one semester
             * means two assessments and no way to say which one is the report.
             */
            $table->unique(['dosen_id', 'tahun_akademik_id'], 'bkd_laporan_unik');

            $table->index(['tahun_akademik_id', 'status']);
        });

        /*
         * The frozen lines.
         *
         * `penugasan_dosen_id` is kept as provenance — it says where the line
         * came from — but nothing reads through it for values. It is nullOnDelete
         * precisely so that deleting an activity a year later cannot take a
         * signed report's line with it.
         */
        Schema::create('bkd_baris', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bkd_laporan_id')->constrained('bkd_laporan')->cascadeOnDelete();

            $table->string('unsur', 24);            // UnsurBkd
            $table->string('kegiatan');
            $table->string('rincian')->nullable();

            $table->unsignedInteger('sks_ratus');

            /*
             * Whether Open Academic computed this line or a person typed it.
             *
             * The distinction an assessor needs first: a derived line can be
             * checked against the class list in seconds, a self-reported one
             * needs its evidence opened. Losing it would make every line equally
             * suspicious, which in practice means none of them get checked.
             */
            $table->boolean('otomatis')->default(false);

            $table->foreignId('penugasan_dosen_id')->nullable()
                ->constrained('penugasan_dosen')->nullOnDelete();

            $table->string('bukti_path')->nullable();

            $table->unsignedSmallInteger('urutan')->default(0);

            $table->timestamps();

            $table->index(['bkd_laporan_id', 'unsur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkd_baris');
        Schema::dropIfExists('bkd_laporan');
    }
};
