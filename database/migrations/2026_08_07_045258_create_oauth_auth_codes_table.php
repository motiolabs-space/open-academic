<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oauth_auth_codes', function (Blueprint $table) {
            $table->char('id', 80)->primary();

            // Published from Passport and deliberately changed from foreignId
            // to uuid. Passport assumes one user table with an auto-increment
            // key; Open Academic has three, so an integer id identifies nobody
            // on its own — id 1 is a student *and* a lecturer *and* a staff
            // member. Issuing an OAuth subject on that basis would hand a
            // consumer one identifier for three different people.
            // See App\Traits\AuthenticatesWithUuid.
            $table->uuid('user_id')->index();
            $table->foreignUuid('client_id');
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->dateTime('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_auth_codes');
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
