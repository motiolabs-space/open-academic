<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the campus's own Neo Feeder says its acts accept (GetDictionary).
 *
 * Field names such as the mobile number on the biodata or the diploma date
 * on Lulus/DO cannot be settled from public documents; the only authority is
 * the dictionary a live installation answers with. One run is a reading of
 * that dictionary for every act this application sends, stored so the
 * comparison with the mappers can be shown again without asking Feeder.
 *
 * The dictionary lives in the campus's database only. It is ministry
 * material and never belongs in the repository (CLAUDE.md §10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeder_dictionary_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // The version Feeder reported, or the operator typed; null when
            // neither said.
            $table->string('versi_feeder', 32)->nullable();
            $table->string('driver', 16); // feeder.driver at the time: live | fake

            $table->unsignedSmallInteger('jumlah_aksi');
            $table->unsignedSmallInteger('jumlah_gagal');

            $table->foreignId('dijalankan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diperiksa_at');

            $table->timestamps();

            $table->index('diperiksa_at', 'feeder_dictionary_runs_diperiksa_index');
        });

        // One act's dictionary within a run: its fields, or why it could not
        // be read.
        Schema::create('feeder_dictionary_acts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feeder_dictionary_run_id')->constrained('feeder_dictionary_runs')->cascadeOnDelete();
            $table->string('act', 96);

            // [{nama, wajib, kunci, tipe, keterangan}], as parsed.
            $table->json('medan')->nullable();

            // Feeder's refusal, or a reply whose shape was not recognised.
            $table->string('galat_kode', 32)->nullable();
            $table->text('galat')->nullable();

            $table->timestamps();

            $table->unique(['feeder_dictionary_run_id', 'act'], 'feeder_dictionary_acts_run_act_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_dictionary_acts');
        Schema::dropIfExists('feeder_dictionary_runs');
    }
};
