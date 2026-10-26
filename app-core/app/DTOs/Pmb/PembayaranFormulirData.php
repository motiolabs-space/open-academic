<?php

declare(strict_types=1);

namespace App\DTOs\Pmb;

use Illuminate\Http\Request;

final readonly class PembayaranFormulirData
{
    public function __construct(
        public int $nominal,
        public string $channel = 'transfer',
        public ?string $referensi = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            nominal: $request->integer('nominal'),
            channel: (string) $request->input('channel', 'transfer'),
            referensi: filled($request->input('referensi')) ? trim((string) $request->input('referensi')) : null,
        );
    }
}
