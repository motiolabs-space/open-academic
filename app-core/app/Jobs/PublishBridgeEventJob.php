<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Services\Bridge\BridgeEventPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one signed webhook, and reschedules itself on failure.
 *
 * Retries back off on the schedule in config/bridge.php rather than Laravel's
 * default immediate retry: a consumer that is down stays down for minutes, and
 * hammering it neither helps them nor gets the event delivered sooner. After
 * the configured attempts the delivery is marked exhausted and left in the log
 * for an operator to replay deliberately.
 */
class PublishBridgeEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retries are scheduled by this job itself, not by the queue worker. */
    public int $tries = 1;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue('bridge');
    }

    public function handle(BridgeEventPublisher $publisher): void
    {
        $delivery = BridgeWebhookDelivery::with('consumer')->find($this->deliveryId);

        if ($delivery === null || $delivery->status === WebhookDeliveryStatus::Delivered) {
            return;
        }

        $consumer = $delivery->consumer;

        if ($consumer === null || !$consumer->is_active || blank($consumer->webhook_url)) {
            $delivery->update(['status' => WebhookDeliveryStatus::Exhausted]);

            return;
        }

        $secret = $consumer->webhook_secret ?: (string) config('bridge.webhooks.secret');

        // Signing with an empty key produces a signature that verifies against
        // an equally empty key on the other side. The delivery would look
        // authenticated while in fact anyone able to reach the consumer's
        // endpoint could forge one — worse than not signing at all, because it
        // is the appearance of a guarantee that is not there.
        if ($secret === '') {
            $delivery->update([
                'status' => WebhookDeliveryStatus::Exhausted,
                'response_body' => 'BRIDGE_WEBHOOK_SECRET belum diisi; pengiriman dibatalkan '
                    .'karena tanda tangan tanpa kunci dapat dipalsukan.',
            ]);

            Log::warning('Bridge webhook dibatalkan: tidak ada kunci penandatanganan.', [
                'consumer' => $consumer->slug,
                'delivery' => $delivery->id,
            ]);

            return;
        }

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;

        $signature = $publisher->tandaTangan($body, $timestamp, $secret);

        $attempts = $delivery->attempts + 1;

        try {
            $response = Http::timeout((int) config('bridge.webhooks.timeout'))
                ->withHeaders([
                    config('bridge.webhooks.signature_header') => $signature,
                    config('bridge.webhooks.timestamp_header') => $timestamp,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($consumer->webhook_url);
        } catch (\Throwable $e) {
            $this->jadwalkanUlang($delivery, $attempts, null, $e->getMessage());

            return;
        }

        if ($response->successful()) {
            $delivery->update([
                'status' => WebhookDeliveryStatus::Delivered,
                'attempts' => $attempts,
                'signature' => $signature,
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'delivered_at' => now(),
                'next_attempt_at' => null,
            ]);

            return;
        }

        $this->jadwalkanUlang(
            $delivery,
            $attempts,
            $response->status(),
            mb_substr($response->body(), 0, 2000),
        );
    }

    private function jadwalkanUlang(
        BridgeWebhookDelivery $delivery,
        int $attempts,
        ?int $status,
        ?string $body,
    ): void {
        $delivery->update([
            'attempts' => $attempts,
            'response_code' => $status,
            'response_body' => $body,
            'status' => $delivery->bolehDicobaLagi()
                ? WebhookDeliveryStatus::Failed
                : WebhookDeliveryStatus::Exhausted,
        ]);

        if (!$delivery->fresh()->bolehDicobaLagi()) {
            return;
        }

        $tunda = $delivery->backoffSeconds();

        $delivery->update(['next_attempt_at' => now()->addSeconds($tunda)]);

        self::dispatch($delivery->id)->delay(now()->addSeconds($tunda));
    }
}
