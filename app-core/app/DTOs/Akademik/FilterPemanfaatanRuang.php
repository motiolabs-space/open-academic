<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Models\Akademik\TahunAkademik;

/**
 * Which rooms a utilisation report covers, and in what order it lists them.
 */
final readonly class FilterPemanfaatanRuang
{
    /** Building, then room code — the order of the master-data screen. */
    public const URUT_GEDUNG = 'gedung';

    public const URUT_TERPENUH = 'terpenuh';

    public const URUT_TERKOSONG = 'terkosong';

    /** @var list<string> */
    public const URUTAN = [self::URUT_GEDUNG, self::URUT_TERPENUH, self::URUT_TERKOSONG];

    public function __construct(
        public TahunAkademik $term,
        public ?int $gedungId = null,
        public ?string $jenis = null,
        public string $urut = self::URUT_GEDUNG,
    ) {}

    /** @param array{gedung_id?: int|string|null, jenis?: ?string, urut?: ?string} $data */
    public static function fromArray(TahunAkademik $term, array $data): self
    {
        $urut = (string) ($data['urut'] ?? self::URUT_GEDUNG);

        return new self(
            $term,
            filled($data['gedung_id'] ?? null) ? (int) $data['gedung_id'] : null,
            filled($data['jenis'] ?? null) ? (string) $data['jenis'] : null,
            in_array($urut, self::URUTAN, true) ? $urut : self::URUT_GEDUNG,
        );
    }
}
