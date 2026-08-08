<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bridge to the accounting system, built as an outbox.
 *
 * Money events are written here first and pushed afterwards, never inside the
 * transaction that created them. Two reasons, and both have bitten real
 * installations:
 *
 *   - Issuing invoices for five thousand students must not wait on five
 *     thousand HTTP calls.
 *   - An accounting system that is down must not be able to fail a billing run.
 *     The debt exists whether or not anybody managed to book it.
 *
 * Shaped after `feeder_sync_logs` rather than inventing a third pattern for the
 * same job — this repo already syncs to an external system exactly this way.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Local record ↔ remote id.
         *
         * easyERP hands back an integer id for every contact and product it
         * creates. Without somewhere to keep it, every invoice push would have
         * to search for the student by name first — and two students called
         * Muhammad Rizki would eventually be billed as one person.
         */
        Schema::create('akuntansi_pemetaan', function (Blueprint $table) {
            $table->id();

            $table->string('jenis', 24);              // JenisEntitasAkuntansi
            $table->string('lokal_kunci', 64);        // uuid mahasiswa, kode komponen tarif
            $table->string('easyerp_id', 64);

            $table->string('label')->nullable();      // for the monitor screen
            $table->timestamp('dipetakan_at');

            $table->timestamps();

            $table->unique(['jenis', 'lokal_kunci'], 'akuntansi_pemetaan_unik');
        });

        /*
         * One row per document owed to the accounting system.
         */
        Schema::create('akuntansi_dokumen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('jenis', 24)->index();     // JenisDokumenAkuntansi

            // Where it came from, for the monitor screen and for tracing a
            // journal line back to the student it belongs to.
            $table->string('lokal_type')->nullable();
            $table->unsignedBigInteger('lokal_id')->nullable();

            /*
             * The idempotency key, and the single most load-bearing column here.
             *
             * Derived from the event — "oa-inv-<uuid tagihan>" — never random.
             * A random key regenerated on retry is not an idempotency key at
             * all; it is a guarantee of duplicates the first time the network
             * drops a response after easyERP has already committed.
             *
             * Unique locally as well as being sent as the Idempotency-Key
             * header, so a duplicate cannot even be queued.
             */
            $table->string('kunci_idempotensi', 96)->unique();

            $table->json('payload');

            /*
             * Amount in whole rupiah, denormalised out of the payload.
             *
             * The monitor screen totals a period's queue, and the export writes
             * a journal sheet; both need the number in a column the database
             * can sum. JSON path arithmetic is not portable across MySQL and
             * PostgreSQL — the same reason the notification category became a
             * real column.
             */
            $table->bigInteger('nominal')->default(0);

            $table->string('status', 16)->default('menunggu')->index(); // StatusDokumenAkuntansi

            $table->string('easyerp_id', 64)->nullable();
            $table->string('easyerp_nomor', 64)->nullable();

            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->timestamp('coba_lagi_setelah')->nullable();
            $table->text('galat')->nullable();
            $table->timestamp('terkirim_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'coba_lagi_setelah']);
            $table->index(['lokal_type', 'lokal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akuntansi_dokumen');
        Schema::dropIfExists('akuntansi_pemetaan');
    }
};
