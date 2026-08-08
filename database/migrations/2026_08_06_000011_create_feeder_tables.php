<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference data pulled from Neo Feeder (agama, wilayah, jenjang,
        // status codes). Feeder is authoritative: local enums are mapped onto
        // these codes rather than assumed to match.
        Schema::create('feeder_refs', function (Blueprint $table) {
            $table->id();

            $table->string('ref_type', 48)->index(); // see config('feeder.references')
            $table->string('code', 64);
            $table->string('name');
            $table->string('parent_code', 64)->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['ref_type', 'code']);
        });

        // Translation table between an Open Academic enum value and the Feeder
        // code for the same concept.
        Schema::create('feeder_mappings', function (Blueprint $table) {
            $table->id();

            $table->string('group', 48); // status_mahasiswa, jenis_keluar, agama ...
            $table->string('local_value', 64);
            $table->string('feeder_code', 64);
            $table->string('feeder_label')->nullable();

            $table->timestamps();

            $table->unique(['group', 'local_value']);
        });

        // The sync ledger. Every push and pull writes exactly one row here.
        //
        // Idempotency: payload_hash is the fingerprint of the payload that was
        // last accepted by Feeder for a given (entity, local row). Re-running a
        // sync compares hashes and records Skipped instead of pushing a
        // duplicate, so an interrupted run can safely be restarted.
        Schema::create('feeder_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('entity', 48)->index(); // see config('feeder.entities')
            $table->string('action', 64); // InsertBiodataMahasiswa, ...
            $table->string('direction', 8)->default('push'); // push|pull

            $table->string('local_type')->nullable();
            $table->unsignedBigInteger('local_id')->nullable();
            $table->string('feeder_id', 64)->nullable();

            $table->foreignId('tahun_akademik_id')->nullable()
                ->constrained('tahun_akademik')->nullOnDelete();

            $table->char('payload_hash', 64)->nullable();
            $table->json('payload')->nullable();

            $table->string('status', 16)->default('pending'); // FeederSyncStatus
            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index(['entity', 'status']);
            $table->index(['local_type', 'local_id']);
        });

        // Result of a pre-flight validation run: rows that would be rejected
        // by Feeder rules, surfaced before anything is pushed.
        Schema::create('feeder_validation_issues', function (Blueprint $table) {
            $table->id();

            $table->string('batch_id', 36)->index();
            $table->string('entity', 48);

            $table->string('local_type')->nullable();
            $table->unsignedBigInteger('local_id')->nullable();
            $table->string('local_label')->nullable(); // NIM / nama, for the report

            $table->string('rule', 64); // nik_required, nidn_invalid, ...
            $table->string('severity', 16)->default('error'); // error|warning
            $table->string('message');

            $table->timestamp('created_at')->nullable();

            $table->index(['entity', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_validation_issues');
        Schema::dropIfExists('feeder_sync_logs');
        Schema::dropIfExists('feeder_mappings');
        Schema::dropIfExists('feeder_refs');
    }
};
