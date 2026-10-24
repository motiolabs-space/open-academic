<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Helpdesk: administrative tickets from students and lecturers to campus units.
 *
 * A ticket is a request for the campus to do something — fix a grade entry,
 * explain an invoice, restore a login. It is not a conversation for its own
 * sake, which is why there is no thread model beyond replies on one ticket, no
 * reactions, and no way for two applicants to see each other's tickets. Those
 * are Open Campus concerns.
 *
 * Index names are explicit wherever Laravel's generated name would run long;
 * MySQL caps identifiers at 64 characters and SQLite does not, so the test
 * suite cannot catch an overrun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_kategori', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nama', 120);
            $table->string('deskripsi', 500)->nullable();

            // Where a ticket of this kind lands before anybody looks at it.
            // Null means it waits in the general queue for a manual disposition.
            $table->foreignId('unit_kerja_id')->nullable()
                ->constrained('unit_kerja')->nullOnDelete();

            // Hours promised for a first substantive response. Null falls back
            // to config('helpdesk.sla_jam_bawaan'); zero means no promise.
            $table->unsignedSmallInteger('sla_jam')->nullable();

            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('helpdesk_tiket', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Quoted over the phone and in email subjects, so short and human.
            $table->string('nomor', 32)->unique();

            // Student or lecturer. Staff file nothing here — they are the ones
            // being asked — so the morph is deliberately narrower than "anyone".
            $table->string('pemohon_type', 120);
            $table->unsignedBigInteger('pemohon_id');

            $table->foreignId('helpdesk_kategori_id')
                ->constrained('helpdesk_kategori')->restrictOnDelete();

            $table->string('judul', 200);
            $table->text('isi');

            $table->string('status', 24)->index();     // StatusTiket
            $table->string('prioritas', 16);           // PrioritasTiket

            // Who is handling it: a unit, and optionally a named person in it.
            $table->foreignId('unit_kerja_id')->nullable()
                ->constrained('unit_kerja')->nullOnDelete();
            $table->foreignId('petugas_staff_id')->nullable()
                ->constrained('staff')->nullOnDelete();

            // Optional attachment on the opening message; replies carry their own.
            $table->string('lampiran_path', 255)->nullable();
            $table->string('lampiran_nama', 255)->nullable();

            /*
             * The SLA clock.
             *
             * tenggat_sla_at is fixed when the ticket is filed; lewat_sla_at is
             * stamped by the scheduled command the first time the deadline is
             * found passed while the ticket still waits on the campus. The
             * command never closes anything — a late ticket is a late ticket,
             * not a resolved one.
             */
            $table->timestamp('tenggat_sla_at')->nullable();
            $table->timestamp('lewat_sla_at')->nullable();

            $table->timestamp('didisposisi_at')->nullable();
            $table->timestamp('ditanggapi_at')->nullable();   // first public staff reply
            $table->timestamp('selesai_at')->nullable();
            $table->timestamp('ditutup_at')->nullable();
            $table->timestamp('balasan_terakhir_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['pemohon_type', 'pemohon_id'], 'helpdesk_tiket_pemohon_index');
            $table->index(['unit_kerja_id', 'status'], 'helpdesk_tiket_unit_status_index');
            $table->index(['status', 'tenggat_sla_at'], 'helpdesk_tiket_sla_index');
        });

        Schema::create('helpdesk_balasan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Replies are never orphaned from their ticket, and a ticket is
            // soft-deleted anyway — restrict rather than cascade.
            $table->foreignId('helpdesk_tiket_id')
                ->constrained('helpdesk_tiket')->restrictOnDelete();

            // Staff, lecturer, or student — whoever wrote it.
            $table->string('penulis_type', 120);
            $table->unsignedBigInteger('penulis_id');

            $table->text('isi');

            // Internal notes are visible to staff only. Stored in the same
            // table so the timeline on the staff screen is one query in order.
            $table->boolean('is_internal')->default(false);

            $table->string('lampiran_path', 255)->nullable();
            $table->string('lampiran_nama', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['helpdesk_tiket_id', 'created_at'], 'helpdesk_balasan_tiket_waktu_index');
            $table->index(['penulis_type', 'penulis_id'], 'helpdesk_balasan_penulis_index');
        });

        Schema::create('helpdesk_faq', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('helpdesk_kategori_id')->nullable()
                ->constrained('helpdesk_kategori')->nullOnDelete();

            $table->string('pertanyaan', 255);
            $table->text('jawaban');
            $table->unsignedSmallInteger('urutan')->default(0);

            // Drafts stay off the public page until somebody says otherwise.
            $table->boolean('is_published')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'urutan'], 'helpdesk_faq_terbit_urutan_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_faq');
        Schema::dropIfExists('helpdesk_balasan');
        Schema::dropIfExists('helpdesk_tiket');
        Schema::dropIfExists('helpdesk_kategori');
    }
};
