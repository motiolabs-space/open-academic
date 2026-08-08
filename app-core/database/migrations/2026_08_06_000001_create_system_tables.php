<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Runtime configuration an institution may change without touching .env
        // (branding, academic calendar toggles, grading scale overrides).
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64)->index();
            $table->string('key', 128);
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string'); // string|int|bool|json
            $table->timestamps();

            $table->unique(['group', 'key']);
        });

        // Append-only audit trail written by HasLogAktivitas via a queued job.
        // Rows are never updated, so there is no updated_at column.
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();

            $table->string('event', 32);
            $table->string('description')->nullable();
            $table->json('changes')->nullable();

            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_name')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
            $table->index('event');
        });

        // Minimal announcement board for the portal dashboards.
        // Long-term this belongs to Open Campus (engagement layer) — keep it
        // small and do not grow it into a CMS here.
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('judul');
            $table->string('slug')->unique();
            $table->text('ringkasan')->nullable();
            $table->longText('isi');

            // Which portals see it: mahasiswa, dosen, staff.
            $table->json('target_roles');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
        Schema::dropIfExists('log_aktivitas');
        Schema::dropIfExists('settings');
    }
};
