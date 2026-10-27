<?php

declare(strict_types=1);

namespace App\DTOs\TugasAkhir;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/** One proposal version as the student submits it. */
final readonly class ProposalData
{
    public function __construct(
        public UploadedFile $berkas,
        public ?string $judul = null,
        public ?string $ringkasan = null,
        public ?int $calonPembimbingId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->validated();

        return new self(
            berkas: $request->file('berkas'),
            judul: filled($data['judul'] ?? null) ? trim((string) $data['judul']) : null,
            ringkasan: filled($data['ringkasan'] ?? null) ? trim((string) $data['ringkasan']) : null,
            calonPembimbingId: filled($data['calon_pembimbing_dosen_id'] ?? null) ? (int) $data['calon_pembimbing_dosen_id'] : null,
        );
    }
}
