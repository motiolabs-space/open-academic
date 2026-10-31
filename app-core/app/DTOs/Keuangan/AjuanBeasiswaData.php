<?php

declare(strict_types=1);

namespace App\DTOs\Keuangan;

use App\Http\Requests\Keuangan\AjukanBeasiswaRequest;
use Illuminate\Http\UploadedFile;

/** A student's scholarship application: why, and the documents asked for. */
final readonly class AjuanBeasiswaData
{
    /** @param array<int, UploadedFile> $berkas in the order the call listed them */
    public function __construct(
        public string $motivasi,
        public array $berkas = [],
    ) {}

    public static function fromRequest(AjukanBeasiswaRequest $request): self
    {
        $berkas = $request->file('berkas', []);

        return new self(
            motivasi: trim((string) $request->string('motivasi')),
            berkas: is_array($berkas) ? $berkas : [],
        );
    }
}
