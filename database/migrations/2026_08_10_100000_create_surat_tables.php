<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letters: everything the campus issues on paper with a number on it.
 *
 * One table for every kind, including the SKPI. They differ in what they say
 * and who may ask for one; they are identical in the two things that make a
 * document trustworthy — a number nobody else holds, and a way for the person
 * receiving it to check that it is real.
 *
 * Building two systems would have meant two numbering schemes and two
 * verification endpoints, and the second one always ends up weaker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat', function (Blueprint $table) {
            $table->id();

            /*
             * The verification handle.
             *
             * Verification is keyed on this, never on the sequential number.
             * A sequential number in a URL is an invitation to walk the range
             * and harvest the names of everyone the campus has issued a letter
             * to — and the answers would be authoritative, which makes the
             * harvest worth doing.
             */
            $table->uuid()->unique();

            $table->string('jenis', 32)->index(); // JenisSurat

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            $table->string('status', 16)->index(); // StatusSurat

            /*
             * The number, and the two columns that guarantee it is never reused.
             *
             * Both are NULL until the letter is actually issued, so a rejected
             * request consumes nothing — a gap in the sequence is a question
             * somebody has to answer during an audit.
             *
             * The composite unique is what holds under concurrency; the service
             * retries against it rather than reading max()+1 and hoping.
             */
            $table->string('nomor', 96)->nullable()->unique();
            $table->unsignedInteger('nomor_urut')->nullable();
            $table->year('tahun')->nullable();

            // What the applicant says they need it for. Printed on letters of
            // introduction, where the purpose is the substance of the document.
            $table->string('keperluan', 255)->nullable();

            /*
             * The facts as they stood when the letter was issued, frozen.
             *
             * Not reassembled at read time. A letter says "this person is an
             * active student" — that was true in March and may not be in
             * September, and the document does not change because the world
             * did. Verification compares the two and says so.
             */
            $table->json('konten')->nullable();

            // Letters that assert a current state expire; an SKPI does not.
            $table->date('berlaku_sampai')->nullable();

            $table->timestamp('diajukan_at')->nullable();
            $table->timestamp('diterbitkan_at')->nullable();
            $table->foreignId('diterbitkan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            /*
             * Revocation, not deletion.
             *
             * A letter that was issued and later withdrawn must still verify —
             * as revoked. Deleting the row would make a real document that
             * somebody is holding report "not found", which reads as a forgery
             * rather than as a withdrawal.
             */
            $table->timestamp('dicabut_at')->nullable();
            $table->string('alasan', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['jenis', 'tahun', 'nomor_urut'], 'surat_urut_unik');
            $table->index(['mahasiswa_id', 'jenis']);
        });

        /*
         * Programme learning outcomes, for the SKPI.
         *
         * The diploma supplement is required to state what a graduate of this
         * programme is able to do — not what this particular student scored.
         * That text belongs to the programme and changes rarely, so it lives
         * here rather than being retyped into every supplement.
         *
         * Bilingual because the regulation requires the supplement in both
         * Indonesian and English, and the English half is exactly the part that
         * gets left blank when it is somebody's job to translate it per
         * graduate.
         */
        Schema::create('prodi_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prodi_id')->constrained('prodi')->cascadeOnDelete();

            $table->string('kode', 16);          // CPL-01
            $table->string('kategori', 32);      // sikap | pengetahuan | keterampilan_umum | keterampilan_khusus
            $table->text('deskripsi');
            $table->text('deskripsi_en')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);

            $table->timestamps();

            $table->unique(['prodi_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prodi_cpl');
        Schema::dropIfExists('surat');
    }
};
