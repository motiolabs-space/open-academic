<?php

declare(strict_types=1);

namespace App\DTOs\Helpdesk;

use App\Enums\PrioritasTiket;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * What an applicant fills in to open a ticket.
 */
final readonly class TiketBaruData
{
    public function __construct(
        public int $kategoriId,
        public string $judul,
        public string $isi,
        public PrioritasTiket $prioritas = PrioritasTiket::Normal,
        public ?UploadedFile $lampiran = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $lampiran = $request->file('lampiran');

        return new self(
            kategoriId: (int) $request->input('helpdesk_kategori_id'),
            judul: trim((string) $request->string('judul')),
            isi: trim((string) $request->string('isi')),
            prioritas: PrioritasTiket::tryFrom((string) $request->string('prioritas')) ?? PrioritasTiket::Normal,
            lampiran: $lampiran instanceof UploadedFile ? $lampiran : null,
        );
    }
}
