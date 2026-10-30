<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\LembagaAkreditasi;
use App\Enums\PeringkatAkreditasi;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An accreditation decree as staff copy it from the paper.
 *
 * The decree date and the date it takes effect are usually the same, so the
 * second defaults to the first; they differ often enough that both are kept.
 */
final readonly class AkreditasiProdiData
{
    public function __construct(
        public LembagaAkreditasi $lembaga,
        public PeringkatAkreditasi $peringkat,
        public string $nomorSk,
        public string $tanggalSk,
        public string $tanggalBerlaku,
        public string $tanggalKedaluwarsa,
        public ?string $keterangan = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            lembaga: LembagaAkreditasi::from($data['lembaga']),
            peringkat: PeringkatAkreditasi::from($data['peringkat']),
            nomorSk: trim((string) $data['nomor_sk']),
            tanggalSk: (string) $data['tanggal_sk'],
            tanggalBerlaku: filled($data['tanggal_berlaku'] ?? null) ? (string) $data['tanggal_berlaku'] : (string) $data['tanggal_sk'],
            tanggalKedaluwarsa: (string) $data['tanggal_kedaluwarsa'],
            keterangan: filled($data['keterangan'] ?? null) ? trim((string) $data['keterangan']) : null,
        );
    }
}
