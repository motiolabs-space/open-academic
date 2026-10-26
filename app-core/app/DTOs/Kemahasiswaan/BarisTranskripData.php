<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

/**
 * One line of an origin transcript, matched to a course here.
 */
final readonly class BarisTranskripData
{
    public function __construct(
        public int $mataKuliahId,
        public string $asalNama,
        public ?int $asalSks = null,
        public ?string $asalNilai = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mataKuliahId: (int) $data['mata_kuliah_id'],
            asalNama: trim((string) $data['asal_nama']),
            asalSks: filled($data['asal_sks'] ?? null) ? (int) $data['asal_sks'] : null,
            asalNilai: filled($data['asal_nilai'] ?? null) ? trim((string) $data['asal_nilai']) : null,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $baris
     * @return array<int, self>
     */
    public static function daftar(array $baris): array
    {
        return array_values(array_map(self::fromArray(...), $baris));
    }
}
