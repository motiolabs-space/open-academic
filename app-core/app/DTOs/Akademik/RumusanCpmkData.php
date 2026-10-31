<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/**
 * A whole CPMK set as the editor submits it: every CPMK with its CPL weights
 * and its sub-CPMK.
 *
 * Saved as one piece, because the invariants span rows — sub-CPMK weights
 * total 100 within a CPMK, CPMK weights total 100 within a CPL — and a row
 * saved alone can only ever be checked against half of them.
 */
final readonly class RumusanCpmkData
{
    /** @param list<CpmkData> $cpmk */
    public function __construct(public array $cpmk) {}

    /**
     * @param array<int, array{kode?: string, rumusan?: string, cpl?: array<int, array{prodi_cpl_id?: int|string, bobot?: int|string}>, sub?: array<int, array{kode?: string, rumusan?: string, bobot?: int|string}>}> $baris
     */
    public static function fromArray(array $baris): self
    {
        return new self(array_values(array_map(
            fn (array $c): CpmkData => new CpmkData(
                kode: trim((string) ($c['kode'] ?? '')),
                rumusan: trim((string) ($c['rumusan'] ?? '')),
                cpl: collect($c['cpl'] ?? [])
                    ->filter(fn (array $p): bool => filled($p['prodi_cpl_id'] ?? null))
                    ->mapWithKeys(fn (array $p): array => [(int) $p['prodi_cpl_id'] => (int) ($p['bobot'] ?? 0)])
                    ->all(),
                sub: array_values(array_map(
                    fn (array $s): SubCpmkData => new SubCpmkData(
                        kode: trim((string) ($s['kode'] ?? '')),
                        rumusan: trim((string) ($s['rumusan'] ?? '')),
                        bobot: (int) ($s['bobot'] ?? 0),
                    ),
                    array_filter($c['sub'] ?? [], fn (array $s): bool => filled($s['rumusan'] ?? null)),
                )),
            ),
            $baris,
        )));
    }
}
