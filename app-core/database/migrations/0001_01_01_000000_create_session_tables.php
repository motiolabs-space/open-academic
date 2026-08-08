<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open Academic has no generic "users" table: staff, lecturers and students
 * are distinct domain entities on distinct auth guards, created by the SDM and
 * Kemahasiswaan migrations. Only the shared session and password-reset
 * plumbing lives here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();

            // Guard-agnostic: holds the authentication identifier of whichever
            // of staff/dosen/mahasiswa owns this session.
            //
            // A uuid, not an integer. The three identity tables have colliding
            // primary keys, so the auth identifier is the UUID
            // (App\Traits\AuthenticatesWithUuid) and this column has to hold
            // one — otherwise every sign-in fails on write.
            $table->uuid('user_id')->nullable()->index();
            $table->string('guard', 32)->nullable()->index();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
