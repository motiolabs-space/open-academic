<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When `student.enrolled` was published for this student — or decided, when
 * Campus Bridge was switched off at the time.
 *
 * Claimed with a conditional update before the event is queued, which is
 * what makes "one event per student" hold across retries and racing
 * requests. Students that existed before this column stay null: they were
 * never announced and never will be (ADR-035 §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->timestamp('bridge_enrolled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropColumn('bridge_enrolled_at');
        });
    }
};
