<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\JenisLogbook;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/** One field-log entry as the student writes it. */
final readonly class LogbookData
{
    public function __construct(
        public JenisLogbook $jenis,
        public string $tanggal,
        public string $judul,
        public ?string $uraian = null,
        public ?UploadedFile $berkas = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->validated();
        $data['berkas'] = $request->file('berkas');

        return self::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            jenis: $data['jenis'] instanceof JenisLogbook ? $data['jenis'] : JenisLogbook::from((string) $data['jenis']),
            tanggal: (string) $data['tanggal'],
            judul: trim((string) $data['judul']),
            uraian: filled($data['uraian'] ?? null) ? trim((string) $data['uraian']) : null,
            berkas: ($data['berkas'] ?? null) instanceof UploadedFile ? $data['berkas'] : null,
        );
    }
}
