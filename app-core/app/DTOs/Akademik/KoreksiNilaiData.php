<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use Illuminate\Http\Request;

/**
 * A correction to a finalised grade.
 *
 * Either the final figure alone, or the component scores it is computed from.
 * Only the second changes the CPMK/CPL attainment: attainment is measured from
 * components, and a corrected total with untouched components says nothing
 * about which outcome the mistake was in (ADR-038 §4).
 */
final readonly class KoreksiNilaiData
{
    /** @param array<int, float> $komponen komponen id => corrected score */
    public function __construct(
        public string $alasan,
        public ?float $nilaiAngka = null,
        public array $komponen = [],
    ) {}

    public function lewatKomponen(): bool
    {
        return $this->komponen !== [];
    }

    public static function fromRequest(Request $request): self
    {
        $komponen = collect((array) $request->input('komponen', []))
            ->reject(fn ($nilai): bool => $nilai === null || $nilai === '')
            ->mapWithKeys(fn ($nilai, $id): array => [(int) $id => round((float) $nilai, 2)])
            ->all();

        return new self(
            alasan: trim((string) $request->string('alasan')),
            nilaiAngka: $request->filled('nilai_angka') ? (float) $request->input('nilai_angka') : null,
            komponen: $komponen,
        );
    }
}
