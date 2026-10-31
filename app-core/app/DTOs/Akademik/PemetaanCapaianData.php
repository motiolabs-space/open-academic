<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/**
 * Which outcomes each grade component of a class measures.
 *
 * Targets are keyed "s:{sub_cpmk id}" or "c:{cpmk id}" — the matrix on the
 * evaluation plan has one column per measurable target, and a CPMK appears as
 * a column only when it has no sub-CPMK of its own.
 */
final readonly class PemetaanCapaianData
{
    /** @param array<int, array<string, int>> $porsi komponen id => [target => share per hundred] */
    public function __construct(public array $porsi) {}

    /** @param array<int|string, array<string, int|string|null>> $isian */
    public static function fromArray(array $isian): self
    {
        $porsi = [];

        foreach ($isian as $komponenId => $baris) {
            foreach ((array) $baris as $sasaran => $nilai) {
                // An empty cell means "does not measure this", not zero weight.
                if ($nilai === null || $nilai === '' || (int) $nilai === 0) {
                    continue;
                }

                $porsi[(int) $komponenId][(string) $sasaran] = (int) $nilai;
            }
        }

        return new self($porsi);
    }
}
