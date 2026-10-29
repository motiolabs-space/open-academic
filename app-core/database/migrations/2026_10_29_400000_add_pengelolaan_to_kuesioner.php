<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the questionnaire screens need beyond the engine of 18 August.
 *
 *  - `prodi_id` narrows the audience inside one role. Null means every
 *    student (or lecturer) on campus. Staff have no study programme, so it is
 *    always null for them — enforced by KuesionerService, not by the column.
 *  - `min_responden` is EDOM's threshold, carried over: an anonymous form's
 *    results stay closed until enough people answered that nobody can be
 *    picked out by elimination. Fixed per questionnaire, like EDOM's period.
 *  - `diterbitkan_at` marks the first publication. From then on the
 *    questions are frozen; an answer must keep meaning what it meant when it
 *    was given.
 *  - `dibuat_oleh_staff_id` names who wrote the instrument.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kuesioner', function (Blueprint $table) {
            $table->foreignId('prodi_id')->nullable()->after('sasaran')
                ->constrained('prodi', indexName: 'kuesioner_prodi_fk')->restrictOnDelete();

            $table->unsignedSmallInteger('min_responden')->default(5)->after('anonim');
            $table->timestamp('diterbitkan_at')->nullable()->after('is_active');

            $table->foreignId('dibuat_oleh_staff_id')->nullable()->after('diterbitkan_at')
                ->constrained('staff', indexName: 'kuesioner_pembuat_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kuesioner', function (Blueprint $table) {
            $table->dropForeign('kuesioner_prodi_fk');
            $table->dropForeign('kuesioner_pembuat_fk');
            $table->dropColumn(['prodi_id', 'min_responden', 'diterbitkan_at', 'dibuat_oleh_staff_id']);
        });
    }
};
