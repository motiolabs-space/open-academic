<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The academic event calendar (#35).
 *
 * Only the events nothing else owns are stored: holidays, campus activities,
 * and administrative deadlines with no screen of their own. KRS and grading
 * windows, published exams, graduation, yudisium, EDOM and admission waves are
 * read from their own tables by KalenderService and never copied here — a copy
 * is a second date that drifts from the one that actually gates anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_peristiwa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('judul', 160);
            $table->string('keterangan', 1000)->nullable();
            $table->string('jenis', 16); // JenisPeristiwa: libur|kegiatan|batas

            // Inclusive on both ends; a one-day event has the same date twice.
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            $table->string('sasaran', 16)->default('semua'); // SasaranPeristiwa
            $table->foreignId('prodi_id')->nullable()->constrained('prodi')->restrictOnDelete();

            $table->string('status', 16)->default('draf'); // StatusPeristiwa

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tanggal_mulai', 'tanggal_selesai'], 'kalender_peristiwa_status_rentang_index');
            $table->index(['jenis', 'tanggal_mulai'], 'kalender_peristiwa_jenis_mulai_index');
        });

        /*
         * Secret iCalendar feed URLs, one live per person.
         *
         * Only the SHA-256 of the token is kept: the URL is a bearer credential
         * that Google or Outlook will poll for years, and a database dump should
         * not hand every feed to whoever reads it. A revoked row is kept with
         * its revocation time rather than deleted, so "this link stopped
         * working" can be answered.
         */
        Schema::create('calendar_feed_tokens', function (Blueprint $table) {
            $table->id();

            $table->string('owner_type', 64);
            $table->unsignedBigInteger('owner_id');

            $table->char('token_hash', 64)->unique('calendar_feed_tokens_token_hash_unique');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'revoked_at'], 'calendar_feed_tokens_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feed_tokens');
        Schema::dropIfExists('kalender_peristiwa');
    }
};
