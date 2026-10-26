<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\HasilUjiKompetensi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** One competency-examination result as staff record it. */
final readonly class HasilUjiKompetensiData
{
    public function __construct(
        public Carbon $tanggalUjian,
        public HasilUjiKompetensi $hasil,
        public ?string $nomorSertifikat = null,
        public ?string $catatan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->validated());
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tanggalUjian: Carbon::parse($data['tanggal_ujian'])->startOfDay(),
            hasil: HasilUjiKompetensi::from($data['hasil']),
            nomorSertifikat: filled($data['nomor_sertifikat'] ?? null) ? trim((string) $data['nomor_sertifikat']) : null,
            catatan: filled($data['catatan'] ?? null) ? trim((string) $data['catatan']) : null,
        );
    }
}
