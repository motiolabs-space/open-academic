<?php

declare(strict_types=1);

namespace App\DTOs\Pmb;

use Illuminate\Http\Request;

/** One programme's terms in a wave. */
final readonly class TawaranProdiData
{
    /** @param array<int, string> $dokumenTambahan */
    public function __construct(
        public int $prodiId,
        public ?int $dayaTampung = null,
        public ?int $biayaFormulir = null,
        public ?float $ambangLulus = null,
        public array $dokumenTambahan = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $angka = static fn (string $kunci): ?int => $request->filled($kunci) ? $request->integer($kunci) : null;

        return new self(
            prodiId: $request->integer('prodi_id'),
            dayaTampung: $angka('daya_tampung'),
            biayaFormulir: $angka('biaya_formulir'),
            ambangLulus: $request->filled('ambang_lulus') ? (float) $request->input('ambang_lulus') : null,
            dokumenTambahan: array_values(array_map('strval', (array) $request->input('dokumen_wajib', []))),
        );
    }
}
