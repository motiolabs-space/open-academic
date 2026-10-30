<?php

declare(strict_types=1);

namespace App\DTOs\Kuesioner;

use App\Enums\TipePertanyaan;
use Illuminate\Http\Request;

/**
 * One question. `opsi` is used for `pilihan` only and ignored otherwise.
 */
final readonly class PertanyaanData
{
    /** @param array<int, string> $opsi */
    public function __construct(
        public string $teks,
        public TipePertanyaan $tipe,
        public array $opsi = [],
        public bool $wajib = true,
    ) {}

    public static function fromRequest(Request $request): self
    {
        // One choice per line: the form is a textarea, which is what a
        // registrar typing "Sangat puas / Puas / Kurang" expects.
        $opsi = collect(preg_split('/\R/', (string) $request->string('opsi')) ?: [])
            ->map(fn (string $baris): string => trim($baris))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return new self(
            teks: trim((string) $request->string('teks')),
            tipe: TipePertanyaan::from((string) $request->string('tipe')),
            opsi: $opsi,
            wajib: $request->boolean('wajib'),
        );
    }
}
