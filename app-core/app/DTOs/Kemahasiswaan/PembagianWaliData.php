<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Http\Requests\Kemahasiswaan\BagiDosenWaliRequest;

/**
 * A cohort to split evenly across a set of advisors.
 *
 * The cohort is one programme, optionally narrowed to one intake year and to
 * students who have no advisor yet — the usual first run after daftar ulang.
 */
final readonly class PembagianWaliData
{
    /** @param array<int, int> $dosenIds */
    public function __construct(
        public int $prodiId,
        public ?int $angkatan,
        public array $dosenIds,
        public bool $hanyaTanpaWali = false,
        public ?string $alasan = null,
    ) {}

    public static function fromRequest(BagiDosenWaliRequest $request): self
    {
        return new self(
            prodiId: $request->prodi()->id,
            angkatan: $request->filled('angkatan') ? $request->integer('angkatan') : null,
            dosenIds: $request->dosen()->pluck('id')->all(),
            hanyaTanpaWali: $request->boolean('hanya_tanpa_wali'),
            alasan: $request->filled('alasan') ? trim((string) $request->string('alasan')) : null,
        );
    }
}
