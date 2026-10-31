<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The local record of every installation ping (ADR-043), so a campus can see
 * exactly what left the building and when.
 *
 * One row per application version, enforced by the unique index: a retry or a
 * second `migrate` reuses the row instead of announcing the same version twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_pings', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('app_version', 32)->unique();
            $table->string('event', 16); // PeristiwaInstalasi
            $table->string('status', 16)->default('queued')->index(); // StatusPingInstalasi

            // Exactly what was (or would have been) sent, rebuilt at send time.
            $table->json('payload')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });

        /*
         * Installations that predate the wizard are already set up.
         *
         * Instalasi::perluSetup() reaches the same answer from "an account
         * exists", but writing the marker here spares those campuses three
         * queries on every request for the rest of their life.
         */
        $adaAkun = collect(['staff', 'dosen', 'mahasiswa'])
            ->contains(fn (string $tabel): bool => Schema::hasTable($tabel) && DB::table($tabel)->exists());

        if ($adaAkun) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'instalasi', 'key' => 'setup_selesai_pada'],
                ['value' => now()->toIso8601String(), 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_pings');
    }
};
