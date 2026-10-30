<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use Illuminate\Http\Request;

/** Which graduates a tracer list covers: a graduation year and a programme, either optional. */
final readonly class FilterLulusan
{
    public function __construct(
        public ?int $tahunLulus = null,
        public ?int $prodiId = null,
        public bool $hanyaPerluVerifikasi = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            tahunLulus: $request->filled('tahun_lulus') ? $request->integer('tahun_lulus') : null,
            prodiId: $request->filled('prodi') ? $request->integer('prodi') : null,
            hanyaPerluVerifikasi: $request->boolean('hanya_perlu'),
        );
    }

    /** @return array<string, int> query-string form, for links and forms that keep the filter */
    public function query(): array
    {
        return array_filter([
            'tahun_lulus' => $this->tahunLulus,
            'prodi' => $this->prodiId,
            'hanya_perlu' => $this->hanyaPerluVerifikasi ? 1 : null,
        ], fn ($v): bool => $v !== null);
    }
}
