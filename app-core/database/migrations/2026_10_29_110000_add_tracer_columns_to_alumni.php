<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the national tracer study needs from a graduate record, and what it
 * gives back (ADR-037 §2).
 *
 * Contact first: the tracer study reaches graduates, and the address they
 * were given here is the campus account that stops working after graduation.
 * A confirmed contact carries who confirmed it and when, so a list two years
 * stale does not look as good as one checked last month.
 *
 * Then the answer: the employment status is collected elsewhere (the
 * ministry's tracer study, or Open Campus's questionnaire) and imported here
 * with its source and survey date, so a later import can tell a newer answer
 * from an older one.
 *
 * `mahasiswa.email_pribadi` is the column the student profile form has been
 * posting to all along; it never existed, so filling it in failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->string('email_pribadi')->nullable()->after('email');
        });

        Schema::table('alumni', function (Blueprint $table) {
            $table->timestamp('kontak_diverifikasi_at')->nullable()->after('telepon');
            $table->foreignId('kontak_diverifikasi_by_staff_id')->nullable()->after('kontak_diverifikasi_at')
                ->constrained('staff', indexName: 'alumni_kontak_verif_staff_fk')->nullOnDelete();

            // Opt-out: the graduate asked that their contact not be passed on.
            // They stay on the tracer list; the contact cells are left blank.
            $table->boolean('kontak_tidak_dibagikan')->default(false)->after('kontak_diverifikasi_by_staff_id');

            $table->unsignedTinyInteger('masa_tunggu_bulan')->nullable()->after('mulai_bekerja');
            $table->string('status_pekerjaan_sumber', 24)->nullable()->after('masa_tunggu_bulan'); // SumberStatusPekerjaan
            $table->date('status_pekerjaan_per')->nullable()->after('status_pekerjaan_sumber');
            $table->timestamp('status_pekerjaan_diimpor_at')->nullable()->after('status_pekerjaan_per');

            $table->index('tahun_lulus', 'alumni_tahun_lulus_idx');
        });
    }

    public function down(): void
    {
        Schema::table('alumni', function (Blueprint $table) {
            $table->dropIndex('alumni_tahun_lulus_idx');
            $table->dropForeign('alumni_kontak_verif_staff_fk');
            $table->dropColumn([
                'kontak_diverifikasi_at', 'kontak_diverifikasi_by_staff_id', 'kontak_tidak_dibagikan',
                'masa_tunggu_bulan', 'status_pekerjaan_sumber', 'status_pekerjaan_per', 'status_pekerjaan_diimpor_at',
            ]);
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropColumn('email_pribadi');
        });
    }
};
