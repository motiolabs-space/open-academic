<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flagship MBKM programmes, claimed at PDDIKTI.
 *
 * An activity already reaches PDDIKTI with its member (#22). Whether it ran
 * under one of the ministry's flagship programmes is a separate claim made
 * afterwards. `flagship_kode` is the programme as PDDIKTI lists it — a code
 * from the reference pull, never a local enum — and `feeder_flagship_id` the
 * activity the accepted claim was made on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aktivitas_mahasiswa', function (Blueprint $table) {
            $table->string('flagship_kode', 64)->nullable()->after('feeder_bimbing_id');
            $table->string('feeder_flagship_id', 64)->nullable()->after('flagship_kode');
        });
    }

    public function down(): void
    {
        Schema::table('aktivitas_mahasiswa', function (Blueprint $table) {
            $table->dropColumn(['flagship_kode', 'feeder_flagship_id']);
        });
    }
};
