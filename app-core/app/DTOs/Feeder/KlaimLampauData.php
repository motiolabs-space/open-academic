<?php

declare(strict_types=1);

namespace App\DTOs\Feeder;

use App\Models\Akademik\Prodi;
use Illuminate\Http\Request;

/**
 * Which past students an operator chose to claim, and why.
 *
 * Rows are named by their report uuid, so what is claimed is exactly what
 * the operator ticked. The reason is optional for clear matches and
 * required by the pre-flight check for any ambiguous row among them.
 */
final readonly class KlaimLampauData
{
    /** @param list<string> $baris report row uuids */
    public function __construct(
        public Prodi $prodi,
        public string $periode,
        public array $baris,
        public ?string $catatan = null,
    ) {}

    public static function fromRequest(Request $request, Prodi $prodi): self
    {
        return self::fromArray($request->all(), $prodi);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, Prodi $prodi): self
    {
        $catatan = trim((string) ($data['catatan'] ?? ''));

        return new self(
            prodi: $prodi,
            periode: (string) ($data['periode'] ?? ''),
            baris: array_values(array_unique(array_map('strval', array_filter((array) ($data['baris'] ?? []), 'filled')))),
            catatan: $catatan === '' ? null : $catatan,
        );
    }
}
