<?php

declare(strict_types=1);

namespace App\DTOs\Pmb;

use Illuminate\Http\Request;

/** The online-registration settings of one wave. */
final readonly class PengaturanGelombangData
{
    /** @param array<int, string> $dokumenWajib */
    public function __construct(
        public bool $daring,
        public array $dokumenWajib,
        public ?string $keterangan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            daring: $request->boolean('is_daring'),
            dokumenWajib: array_values(array_map('strval', (array) $request->input('dokumen_wajib', []))),
            keterangan: filled($request->input('keterangan')) ? trim((string) $request->input('keterangan')) : null,
        );
    }
}
