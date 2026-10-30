<?php

declare(strict_types=1);

namespace App\DTOs\Bridge;

use Illuminate\Http\Request;

/**
 * An application allowed to read through Campus Bridge, as an operator
 * describes it. The slug is read on registration only: the artisan command
 * and existing integrations know the consumer by it.
 */
final readonly class KonsumenBridgeData
{
    /** @param array<int, string> $scopes */
    public function __construct(
        public string $nama,
        public string $slug,
        public ?string $deskripsi,
        public ?string $baseUrl,
        public array $scopes,
        public bool $aktif = true,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            nama: trim((string) $request->string('nama')),
            slug: trim((string) $request->string('slug')),
            deskripsi: $request->filled('deskripsi') ? trim((string) $request->string('deskripsi')) : null,
            baseUrl: $request->filled('base_url') ? trim((string) $request->string('base_url')) : null,
            scopes: array_values(array_unique(array_map('strval', (array) $request->input('scopes', [])))),
            aktif: $request->boolean('is_active', true),
        );
    }
}
