<?php

declare(strict_types=1);

namespace App\DTOs\Helpdesk;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * A reply on a ticket.
 *
 * `internal` is a request, not a right: the service refuses an internal note
 * from anybody who is not staff, whatever the flag says.
 */
final readonly class BalasanData
{
    public function __construct(
        public string $isi,
        public bool $internal = false,
        public ?UploadedFile $lampiran = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $lampiran = $request->file('lampiran');

        return new self(
            isi: trim((string) $request->string('isi')),
            internal: $request->boolean('internal'),
            lampiran: $lampiran instanceof UploadedFile ? $lampiran : null,
        );
    }
}
