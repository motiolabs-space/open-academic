<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scholarships: the scheme, and who holds one.
 *
 * Two tables because they answer different questions and change at different
 * rates. A scheme is written once and lasts years; an award belongs to one
 * student for one stretch of terms. Flattening them would mean retyping the
 * coverage rule onto every recipient, which is how two students on the same
 * scholarship end up with different amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->string('jenis', 16)->index(); // JenisBeasiswa

            /*
             * Who ultimately pays.
             *
             * For an internal scheme the campus absorbs it. For an external one
             * a sponsor does, and this is the name that appears beside the
             * reduction so the money is traceable to somebody.
             *
             * Billing that sponsor is a receivable in the finance system, not
             * here — see docs/KEUANGAN.md. What this module guarantees is that
             * the campus can always say who a discount was granted on behalf of.
             */
            $table->string('penyandang')->nullable();

            /*
             * Coverage, expressed one of two ways.
             *
             * Percent for the common case ("full tuition", "50%"), a fixed
             * amount for sponsor schemes that pay a stated sum per term. Exactly
             * one is set; the service refuses both or neither.
             */
            $table->unsignedTinyInteger('persen')->nullable();
            $table->unsignedBigInteger('nominal')->nullable();

            /*
             * Which charge lines it applies to, by tarif component name.
             *
             * Null covers everything. A scholarship that pays tuition but not
             * the laboratory fee is ordinary, and without this it would silently
             * pay both.
             */
            $table->json('komponen')->nullable();

            $table->unsignedSmallInteger('kuota')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('beasiswa_penerima', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('beasiswa_id')->constrained('beasiswa')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            /*
             * The stretch of terms it covers.
             *
             * mulai is required; selesai null means open-ended, which is how a
             * four-year award is usually recorded at the point it is granted.
             */
            $table->foreignId('tahun_akademik_mulai_id')->constrained('tahun_akademik')->restrictOnDelete();
            $table->foreignId('tahun_akademik_selesai_id')->nullable()->constrained('tahun_akademik')->nullOnDelete();

            $table->string('status', 16)->index(); // StatusPenerima
            $table->string('nomor_sk', 64)->nullable();
            $table->string('catatan', 500)->nullable();

            $table->foreignId('diputus_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diputus_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * One live award per student per scheme, held by the database.
             *
             * Carries "{beasiswa_id}:{mahasiswa_id}" while active and NULL
             * otherwise, so a student's history under a scheme coexists while a
             * second concurrent award is refused. Same shape as
             * tugas_akhir.mahasiswa_aktif_id and konversi_kredit.kunci_aktif,
             * and for the same reason: a partial index is not portable.
             *
             * Two live awards would apply the coverage twice to one invoice.
             */
            $table->string('kunci_aktif', 48)->nullable()->unique();

            $table->index(['mahasiswa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beasiswa_penerima');
        Schema::dropIfExists('beasiswa');
    }
};
