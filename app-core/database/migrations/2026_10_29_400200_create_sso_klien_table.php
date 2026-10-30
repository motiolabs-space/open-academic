<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The campus's own record of each SSO client, beside Passport's table.
 *
 * Passport's `oauth_clients` stays as Passport ships it. What the campus decides
 * about a client lives here: whether it may skip the consent screen, who
 * registered it, when its secret was last rotated, and when it was revoked —
 * audited through HasLogAktivitas, which needs an integer key that a UUID
 * client id is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_klien', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignUuid('oauth_client_id')
                ->constrained('oauth_clients', indexName: 'sso_klien_oauth_client_fk')->cascadeOnDelete();

            // Skips the consent screen. Only for applications the campus runs
            // itself; SSO_FIRST_PARTY in .env still works alongside this.
            $table->boolean('is_first_party')->default(false);

            $table->foreignId('didaftarkan_oleh_staff_id')->nullable()
                ->constrained('staff', indexName: 'sso_klien_pendaftar_fk')->nullOnDelete();

            $table->timestamp('rahasia_dirotasi_at')->nullable();

            // Final: a revoked client is re-registered, not revived.
            $table->timestamp('dicabut_at')->nullable();

            $table->timestamps();

            $table->unique('oauth_client_id', 'sso_klien_oauth_client_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_klien');
    }
};
