<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Findings from comparing this application against PDDIKTI.
 *
 * Stored rather than rendered once, for the same reason the validator stores
 * its issues: a comparison produces a work list that staff clear over days,
 * and a screen that recomputes on every visit cannot show whether anything
 * moved since yesterday.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeder_diffs', function (Blueprint $table) {
            $table->id();

            $table->string('batch_id', 36)->index();
            $table->string('entity', 40);
            $table->string('term_kode', 10);

            $table->string('jenis', 20);

            // The natural key both sides were matched on, e.g. a class code or
            // a registration id. Kept as text: it is composite for most
            // entities, and its parts differ per entity.
            $table->string('kunci', 191);
            $table->string('label', 191)->nullable();

            // Null for rows that exist only in Feeder — there is no local row
            // to point at, and that is precisely the finding.
            $table->string('local_type', 191)->nullable();
            $table->unsignedBigInteger('local_id')->nullable();

            /*
             * field => {lokal, feeder} for a mismatch; null otherwise.
             *
             * Only fields this application actually sends are recorded. Feeder
             * returns a great deal more, and reporting differences in fields we
             * never claimed to own would bury the ones we do.
             */
            $table->json('selisih')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['entity', 'term_kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_diffs');
    }
};
