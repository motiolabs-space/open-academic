<?php

declare(strict_types=1);

namespace App\Services\Bridge;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\PublishBridgeEventJob;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Bridge\BridgeWebhookDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Publishes domain events to subscribed consumer applications.
 *
 * Open Campus keeps its feed, portfolios and IKU figures fresh by subscribing
 * rather than polling; polling a registry every few minutes to notice one
 * approved study plan is a waste of both systems.
 *
 * Delivery is queued and signed. Publishing never blocks the academic action
 * that triggered it — a lecturer finalising grades must not wait on, or be
 * failed by, someone else's HTTP endpoint being slow.
 */
class BridgeEventPublisher
{
    /**
     * Queues one delivery per subscribed consumer.
     *
     * @param array<string, mixed> $payload
     * @return int deliveries queued
     */
    public function publish(string $event, array $payload): int
    {
        if (!config('bridge.enabled')) {
            return 0;
        }

        if (!in_array($event, config('bridge.events'), true)) {
            // An unknown event name is a bug in the caller, not something to
            // deliver: consumers would have no way to interpret it.
            throw new \InvalidArgumentException("Event Bridge \"{$event}\" tidak terdaftar pada config/bridge.php.");
        }

        $consumers = $this->pelanggan($event);

        foreach ($consumers as $consumer) {
            $delivery = BridgeWebhookDelivery::create([
                'bridge_consumer_id' => $consumer->id,
                'event' => $event,
                'payload' => $this->amplop($event, $payload),
                'status' => WebhookDeliveryStatus::Pending,
            ]);

            PublishBridgeEventJob::dispatch($delivery->id);
        }

        return $consumers->count();
    }

    /**
     * The envelope a consumer receives.
     *
     * `id` lets a consumer detect a redelivery, and `occurred_at` lets it
     * order events that arrive out of sequence after a retry.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function amplop(string $event, array $payload): array
    {
        return [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'institution' => config('branding.institution.code'),
            'data' => $payload,
        ];
    }

    /**
     * The HMAC a consumer verifies before trusting a payload.
     *
     * The timestamp is signed together with the body so a captured delivery
     * cannot be replayed later with a fresh timestamp.
     */
    public function tandaTangan(string $body, string $timestamp, string $secret): string
    {
        return hash_hmac(
            (string) config('bridge.webhooks.algorithm'),
            $timestamp.'.'.$body,
            $secret,
        );
    }

    /** @return Collection<int, BridgeConsumer> */
    private function pelanggan(string $event): Collection
    {
        return BridgeConsumer::aktif()
            ->whereNotNull('webhook_url')
            ->get()
            ->filter(fn (BridgeConsumer $c): bool => $c->subscribesTo($event))
            ->values();
    }
}
