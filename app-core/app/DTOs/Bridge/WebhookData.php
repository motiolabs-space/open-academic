<?php

declare(strict_types=1);

namespace App\DTOs\Bridge;

use Illuminate\Http\Request;

/** Where a consumer wants events delivered, and which ones. */
final readonly class WebhookData
{
    /** @param array<int, string> $events */
    public function __construct(
        public ?string $url,
        public array $events = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            url: $request->filled('webhook_url') ? trim((string) $request->string('webhook_url')) : null,
            events: array_values(array_unique(array_map('strval', (array) $request->input('webhook_events', [])))),
        );
    }
}
