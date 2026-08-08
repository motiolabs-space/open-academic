<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDDIKTI reports a study plan one course at a time: InsertKRSMahasiswa carries
 * a single (student, class) pair, and the identifier it returns belongs to that
 * pair — not to the plan as a whole.
 *
 * The identifier columns were originally placed on `krs`, which is one level
 * too high; a plan of six courses has six Feeder records, not one. The columns
 * on `krs` are left in place but are no longer written by any mapper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('krs_detail', function (Blueprint $table) {
            $table->string('feeder_id', 64)->nullable()->index()->after('status');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
        });
    }

    public function down(): void
    {
        Schema::table('krs_detail', function (Blueprint $table) {
            $table->dropColumn(['feeder_id', 'feeder_synced_at']);
        });
    }
};
