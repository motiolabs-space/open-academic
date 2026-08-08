<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use Illuminate\Http\Request;

/**
 * An academic advisor's decision on a study plan.
 *
 * A rejection without a note is useless to the student who has to fix it, so
 * the note is required for a rejection and optional for an approval — enforced
 * by the FormRequest, mirrored here so the service can be called from a command
 * or a test without going through HTTP.
 */
final readonly class KeputusanWaliData
{
    public function __construct(
        public bool $disetujui,
        public ?string $catatan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            disetujui: $request->boolean('disetujui'),
            catatan: $request->filled('catatan') ? trim((string) $request->string('catatan')) : null,
        );
    }

    public static function setujui(?string $catatan = null): self
    {
        return new self(disetujui: true, catatan: $catatan);
    }

    public static function tolak(string $catatan): self
    {
        return new self(disetujui: false, catatan: $catatan);
    }
}
