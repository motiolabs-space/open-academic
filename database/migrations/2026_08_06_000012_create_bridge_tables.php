<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An external application allowed to read Open Academic data — Open
        // Campus first, but the contract is deliberately generic. Consumers
        // never touch the database; they hold a scoped token and an endpoint.
        Schema::create('bridge_consumers', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nama');
            $table->string('slug', 64)->unique();
            $table->string('deskripsi')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('base_url')->nullable();

            // Only the resources listed here may be read by this consumer.
            $table->json('scopes');

            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->json('webhook_events')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Queued, signed, retried event delivery.
        Schema::create('bridge_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('bridge_consumer_id')->constrained('bridge_consumers')->cascadeOnDelete();

            $table->string('event', 64)->index();
            $table->json('payload');
            $table->string('signature', 128)->nullable();

            $table->string('status', 16)->default('pending'); // WebhookDeliveryStatus
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();

            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index(['bridge_consumer_id', 'status']);
        });

        // Lightweight access log backing the API usage chart on the Bridge
        // console. Prune with a scheduled command, not by growing forever.
        Schema::create('bridge_api_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bridge_consumer_id')->nullable()
                ->constrained('bridge_consumers')->nullOnDelete();

            $table->string('method', 8);
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_api_requests');
        Schema::dropIfExists('bridge_webhook_deliveries');
        Schema::dropIfExists('bridge_consumers');
    }
};
