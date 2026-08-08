<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications.
 *
 * Until now the whole system was pull-only: nothing ever told anyone anything.
 * A study plan was approved and the student who submitted it found out by
 * logging in and looking; an invoice fell due in silence. Those two carry
 * administrative consequences — a lost semester, a blocked enrolment — so the
 * silence was not a missing convenience.
 *
 * Three tables:
 *
 *  - notifications  Laravel's own shape. Polymorphic because there is no generic
 *                   users table here; the type column is what keeps mahasiswa 1
 *                   and dosen 1 apart, so no UUID indirection is needed (unlike
 *                   the Passport tables, where the identifier stands alone).
 *
 *  - preferensi_notifikasi  What a person has chosen to mute. Absence means the
 *                   default, so a campus that never touches this ships working.
 *
 *  - notifikasi_kunci  Which reminders have already gone out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');

            /*
             * The category, lifted out of the JSON payload into its own column.
             *
             * Filtering `data->kategori` would work on MySQL and SQLite and
             * fail on PostgreSQL, where a text column has no JSON operators —
             * exactly the class of difference BASIS-DATA.md warns about. A plain
             * indexed column costs 32 bytes and behaves the same everywhere.
             *
             * Written by DatabaseKategoriChannel, which is why the stock
             * database channel is replaced in AppServiceProvider.
             */
            $table->string('kategori', 32)->nullable()->index();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The unread count runs on every page load of every portal.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_belum_dibaca_index');
        });

        Schema::create('preferensi_notifikasi', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');

            $table->string('kategori', 32); // KategoriNotifikasi

            /*
             * Two channels, and they are not equally optional.
             *
             * The in-app record is the authoritative one — it is what a student
             * points at when they say they were never told — so mandatory
             * categories cannot switch it off. Email is delivery convenience and
             * may always be muted.
             *
             * KategoriNotifikasi::wajib() decides which is which; this column
             * only records the choice, and the service ignores it where the
             * category does not permit one.
             */
            $table->boolean('aplikasi')->default(true);
            $table->boolean('email')->default(true);

            $table->timestamps();

            $table->unique(['notifiable_type', 'notifiable_id', 'kategori'], 'preferensi_notifikasi_unik');
        });

        /*
         * One row per reminder already delivered.
         *
         * Deadline reminders run on a schedule, which means the same job sees
         * the same overdue invoice every night. Without this it would send the
         * same message every night — and a reminder that arrives nightly is
         * worse than none, because people learn to ignore it and then miss the
         * one that mattered.
         *
         * A plain string key rather than querying the notifications JSON:
         * JSON path syntax differs across engines, and this table has to work
         * the same on all of them.
         */
        Schema::create('notifikasi_kunci', function (Blueprint $table) {
            $table->id();
            $table->morphs('notifiable');
            $table->string('kunci', 160);
            $table->timestamp('created_at')->nullable();

            $table->unique(['notifiable_type', 'notifiable_id', 'kunci'], 'notifikasi_kunci_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_kunci');
        Schema::dropIfExists('preferensi_notifikasi');
        Schema::dropIfExists('notifications');
    }
};
