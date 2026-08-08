<?php

declare(strict_types=1);

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\PublishBridgeEventJob;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Services\Bridge\BridgeEventPublisher;
use Illuminate\Support\Facades\Http;

function pengirimanUji(string $rahasiaKonsumen = ''): BridgeWebhookDelivery
{
    $consumer = BridgeConsumer::create([
        'slug' => 'uji',
        'nama' => 'Konsumen Uji',
        'scopes' => ['students.read'],
        'webhook_url' => 'https://konsumen.test/webhook',
        'webhook_events' => ['student.graduated'],
        'webhook_secret' => $rahasiaKonsumen,
        'is_active' => true,
    ]);

    return BridgeWebhookDelivery::create([
        'bridge_consumer_id' => $consumer->id,
        'event' => 'student.graduated',
        'payload' => ['nim' => '2026001'],
        'status' => WebhookDeliveryStatus::Pending,
        'attempts' => 0,
    ]);
}

it('menolak mengirim webhook tanpa kunci penandatanganan', function () {
    config(['bridge.webhooks.secret' => '']);
    Http::fake();

    $delivery = pengirimanUji();

    (new PublishBridgeEventJob($delivery->id))->handle(app(BridgeEventPublisher::class));

    Http::assertNothingSent();

    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Exhausted);
});

it('mengirim webhook bertanda tangan ketika kunci tersedia', function () {
    config(['bridge.webhooks.secret' => 'kunci-rahasia-uji']);
    Http::fake(['*' => Http::response('', 200)]);

    $delivery = pengirimanUji();

    (new PublishBridgeEventJob($delivery->id))->handle(app(BridgeEventPublisher::class));

    Http::assertSent(function ($request) {
        $signature = $request->header(config('bridge.webhooks.signature_header'))[0] ?? '';
        $timestamp = $request->header(config('bridge.webhooks.timestamp_header'))[0] ?? '';

        return hash_equals(
            hash_hmac('sha256', $timestamp.'.'.$request->body(), 'kunci-rahasia-uji'),
            $signature,
        );
    });

    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Delivered);
});
